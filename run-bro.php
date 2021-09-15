#!/usr/bin/php

<?php
/**
 * run-bro.php - run this first - next: run-tshark.php
 *
 * Created by: Charles Wheelus  -  5/1/17
 */

$time_start = microtime(true);

openlog("broproc", LOG_PID | LOG_PERROR, LOG_LOCAL1);

require('./project_settings.php');

$conn = pg_connect(CON);

$logfile=fopen(LOGDIR.'run-bro.log','a+');
// error_reporting(E_ERROR | E_PARSE);  - moved to project_settings.php

$query1="SELECT filename FROM samples WHERE (bro_proc IS NULL) AND (skip='FALSE') LIMIT ".BROLIMIT;
$result1=pg_query($conn,$query1);
if(pg_num_rows($result1)==0)
{
    $now=time();
    fwrite($logfile,date('m-d-Y-G:i:s',$now)." no files found to process...exiting\n");
    fclose($logfile);
    pg_close($conn);
    exit;
}
else
{
    while($row1 = pg_fetch_row($result1))
    {
        $files[]=$row1[0];
    }
}



chdir(DATADIR);
$count=0;

foreach($files as $file)
{

    if (DEBUG) print($file . "\n");

    if (mkdir(WORKDIR . $file)) {}
    else
    {
        fwrite($logfile, "Unable to make directory: " . $file . "\n");
        continue;
    }
    chdir(WORKDIR . $file);
    $now = time();
    fwrite($logfile, date('m-d-Y-G:i:s', $now) . " " . $file . "\n");
    // /opt/bro/bin/bro -r <pcap-file>
    $output = shell_exec('/opt/bro/bin/bro -r '.DATADIR.$file.' 2>&1');
    if (!empty($output))
    {
        $now = time();

        fwrite($logfile, date('m-d-Y-G:i:s', $now) . " " . $file . ': ' . $output . "\n");
    }
    $brofiles=scandir(WORKDIR.$file);
    $now = time();
    if(!empty($brofiles))
    {
        $query2="UPDATE samples SET bro_proc = '".date('m-d-Y G:i:s',$now)."' where filename='".$file."'";
        $results2=pg_query($conn,$query2);
        if($results2)
        {
            $now = time();
            fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file.": processed by bro \n");
        }
    }


    $count++;
}

$now=time();
fwrite($logfile,"END: ".date('m-d-Y-G:i:s',$now)."\n");
fclose($logfile);
pg_close($conn);
$time_finish = microtime(true);
$total_time = $time_finish - $time_start;

syslog(LOG_INFO, "files=".$count." seconds=".$total_time." limit=".BROLIMIT."\n");
closelog();
?>

