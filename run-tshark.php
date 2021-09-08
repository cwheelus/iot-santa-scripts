#!/usr/bin/php

<?php
/**
 * run-tshark.php - #2 after run-bro.php - next: push-packets.php
 *
 * Created by: Charles Wheelus  -  5/3/17
 */

$time_start = microtime(true);

openlog("tsharkproc", LOG_PID | LOG_PERROR, LOG_LOCAL1);

require('./project_settings.php');


$conn = pg_connect(CON);

$now=time();
$logfile=fopen(LOGDIR.'run-tshark.log','a+');

$query1="SELECT filename FROM samples WHERE (bro_proc IS NOT NULL) AND (tshark_proc IS NULL) AND (skip='FALSE') LIMIT ".SHARKLIMIT;
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
    if(DEBUG) print($file."\n");
    if(chdir(WORKDIR.$file))
    {
        $output=array();
        $now=time();
        fwrite($logfile,date('m-d-Y-G:i:s',$now)." ".$file."\n");
        /* /usr/sbin/tshark -r <file> -t e -T fields -e frame.number -e frame.time_epoch -e frame.protocols
            -e ip.src -e ip.dst -e udp.srcport -e tcp.srcport -e sctp.srcport -e udp.dstport -e tcp.dstport
            -e sctp.dstport -e frame.len -e expert.message

        This version outputs tab delimited, which has some issues, csv makes life easier later :)
        system('/usr/sbin/tshark -r '.DATADIR.$file.' -t e -T fields -e frame.number -e frame.time_epoch '
            .' -e frame.protocols -e ip.src -e ip.dst -e udp.srcport -e tcp.srcport -e sctp.srcport -e udp.dstport '
            .' -e tcp.dstport -e sctp.dstport -e ip.len -e expert.message '
            .' >packet.txt 2>>'.LOGDIR.'run-tshark.log');

        */
        system('/usr/sbin/tshark -r '.DATADIR.$file.' -t e -T fields -e frame.number -e frame.time_epoch '
            .' -e frame.protocols -e ip.src -e ip.dst -e udp.srcport -e tcp.srcport -e sctp.srcport -e udp.dstport '
            .' -e tcp.dstport -e sctp.dstport -e ip.len -e expert.message  -E header=n -E separator=, '
            .' -E occurrence=f >packet.txt 2>>'.LOGDIR.'run-tshark.log');


    }
    else
    {
        fwrite($logfile,"Unable to change directory: ".$file."\n");
        continue;
    }


    if(file_exists(WORKDIR.$file."/packet.txt"))
    {
        if(DEBUG) print("packet.txt found\n");
        if(filesize(WORKDIR.$file."/packet.txt")>0)
        {
            if(DEBUG) print("packet.txt filesize greater than zero\n");
            $query2="UPDATE samples SET tshark_proc = '".date('m-d-Y G:i:s',$now)."' where filename='".$file."'";

            $results2=pg_query($conn,$query2);
            if($results2)
            {
                $now = time();
                fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file.": processed by tshark \n");
            }
        }
    }
    else
    {
        $now = time();
        fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file.": packet.txt not found - ".$file." not processed \n");
    }

    $count++;

}


fclose($logfile);
pg_close($conn);

$time_finish = microtime(true);
$total_time = $time_finish - $time_start;

syslog(LOG_INFO, "files=".$count." seconds=".$total_time." limit=".SHARKLIMIT."\n");
closelog();
?>
