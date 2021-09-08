#!/usr/bin/php

<?php
/**
 * tag-packets.php - #5 after push-sessions.php - next: calc-features.php
 *
 * Created by: Charles Wheelus  -  5/9/17
 */


/***********************************************************************************************************************
 * add_duration
 **********************************************************************************************************************/
function add_duration($stime,$dur)
{
    // $stime = 2012-04-05 01:57:09.807032
    // $dur = 15.123456

    $subseconds[1]=0;
    if($dur==0) $sftime=$stime;
    else
    {
        $datetime=explode(" ",$stime);
        $date=$datetime[0];
        $time=$datetime[1];


        $breaktime=explode(":",$time);
        $hours=$breaktime[0];
        $minutes=$breaktime[1];
        $seconds=$breaktime[2];

        $seconds+=$dur;
        if(DEBUG) print("seconds:".$seconds."\n");
        if($seconds>=60)
        {
            $finalsecs=$seconds%60;
            $subseconds=explode(".",$seconds);
            $carryminutes=floor($subseconds[0]/60);
            if(DEBUG)     print("carrymin:".$carryminutes."\n");
        }
        else
        {
            $carryminutes=0;
            $subseconds=explode(".",$seconds);
            $finalsecs=floor($seconds);
        }
        if(DEBUG) print("finalsecs:".$finalsecs."\n");

        $minutes+=$carryminutes;
        if(DEBUG) print("minutes:".$minutes."\n");
        if($minutes>=60)
        {
            $finalminutes=$minutes%60;
            $carryhours=floor($minutes/60);
        }
        else
        {
            $carryhours=0;
            $finalminutes=$minutes;
        }
        if(DEBUG) print("carryhours:".$carryhours."\n");
        $hours+=$carryhours;
        if($hours>=24)
        {
            $finalhours=$hours%24;
            $carrydays=floor($hours/24);
        }
        else
        {
            $carrydays=0;
            $finalhours=$hours;
        }
        if(DEBUG) PRINT("CARRYDAYS:".$carrydays."\n");

        $ndate=date_create($date);
        if($carrydays > 0)
        {
            date_add($ndate,date_interval_create_from_date_string($carrydays.' days'));
        }
        if(empty($subseconds[1])) $subseconds[1]=0;
        $sftime=date_format($ndate, 'Y-m-d')." ".$finalhours.":".$finalminutes.":".$finalsecs.".".$subseconds[1];

    }
    if(DEBUG) print("sftime:".$sftime."\n\n");
    return $sftime;
}



/***********************************************************************************************************************
 * MAIN
 **********************************************************************************************************************/
function MAIN(){}


openlog("tagpkt", LOG_PID | LOG_PERROR, LOG_LOCAL1);
require('./project_settings.php');
$conn = pg_connect(CON);
$mypid=getmypid();

exec('echo $STY',$myscreen);
$logfile=fopen(LOGDIR.'tag-packets-'.$myscreen[0].'-'.$mypid.'.log','a+');
define('STATUSFILE',HOME.PROJECT.'/runstatus.txt');


// prepared statement for postgresql
$prepare1 = pg_prepare($conn, "findsessions",
    "select uid,filename,stime,duration,orig_ip,resp_ip,orig_packets,resp_packets,orig_port,resp_port from s
    where filename=$1 AND verified='f' AND ignore='f'");

$prepare2 = pg_prepare($conn, "findpackets",
    "select pid from p where filename=$1 AND (src_ip_address=$2 AND dst_ip_address=$3 AND src_port=$4 and dst_port=$5)
    OR (src_ip_address=$3 AND dst_ip_address=$2 AND src_port=$5 AND dst_port=$4) AND ptime>=$6 AND ptime<=$7");

$prepare3 = pg_prepare($conn, "tagpackets",
    "UPDATE p set uid=$1 WHERE pid=$2");

$prepare4 = pg_prepare($conn, "updatesession",
    "UPDATE s set exp_packets=$1, found_packets=$2, pktfnd_ratio=$3, verified=$4 WHERE uid=$5");

$query1="SELECT filename FROM samples WHERE (pushed_sessions IS NOT NULL) AND (tagged_packets IS NULL) "
    ." AND (skip='FALSE') LIMIT 1 FOR UPDATE";
// If we were using postgreSQL v9.5 or later this query should be modified as follows to increase parallel performance
// .... FOR UPDATE SKIP LOCKED
// which would prevent long wait times when two concurrent processes select "at the same time".


chdir(DATADIR);

$keeprunning=file(STATUSFILE);
while(count($keeprunning)>0)  // Main Loop continues to run unless the file "runstatus.txt" is empty
{
    // first check to see if we should keep running or shut down
    $keeprunning=file(STATUSFILE);
    if(count($keeprunning)===0) continue;  // if runstatus.txt is empty, continue past so we can jump out of the while loop

    // keep running
    $now=time();
    $time_start = microtime(true);
    fwrite($logfile,date('m-d-Y-G:i:s',$now)." Start postgreSQL transaction - ".microtime()."\n");
    pg_query($conn,"BEGIN WORK");
    pg_query($conn,"SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");

    $result1=pg_query($conn,$query1);

    $error=pg_last_error($conn);

    if($error)
    {
        $now = time();
        fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file." ".$error." \n");
        fwrite($logfile,date('m-d-Y-G:i:s',$now)." Rolling back postgreSQL transaction\n");
        pg_query($conn,"ROLLBACK WORK");
        continue;
    }

    $files=array();
    while($row1 = pg_fetch_row($result1))
    {
        $files[]=$row1[0];
    }


    if(count($files)===0)
    {
        $now=time();
        fwrite($logfile,date('m-d-Y-G:i:s',$now)." no files found to process...exiting\n");
        fwrite($logfile,date('m-d-Y-G:i:s',$now)." Rolling back postgreSQL transaction\n");
        pg_query($conn,"ROLLBACK WORK");

        fclose($logfile);
        pg_close($conn);
        continue;

    }
    else
    {
        $now=time();
        $query7="UPDATE samples SET tagged_packets = '".date('m-d-Y G:i:s',$now)."' where filename='".$files[0]."'";
        $results7=pg_query($conn,$query7);

    }
    $now=time();
    pg_query($conn,"COMMIT WORK");
    fwrite($logfile,date('m-d-Y-G:i:s',$now)." End postgreSQL transaction - ".microtime()."\n");

    $count=0;
    $total_pkts_fnd=0;
    foreach ($files as $file)
    {
        $now=time();
        fwrite($logfile,date('m-d-Y-G:i:s',$now)." Begin processing: ".$file."\n");
        $result2=pg_execute($conn,"findsessions",array($file));
        $session_count=0;

        while ($row2 = pg_fetch_row($result2))
        {
            $session_count++;
            $uid=$row2[0];
            $filename=$row2[1];
            $stime=$row2[2];
            $duration=$row2[3];
            $orig_ip=$row2[4];
            $resp_ip=$row2[5];
            $orig_packets=$row2[6];
            $resp_packets=$row2[7];
            $orig_port=$row2[8];
            $resp_port=$row2[9];
            $sftime=add_duration($stime,$duration);
            //print($uid."\n");

            if(strlen($orig_port)==0)   $orig_port=NULL;
            if(strlen($resp_port)==0)   $resp_port=NULL;

            $result3=pg_execute($conn,"findpackets",array($file,$orig_ip,$resp_ip,$orig_port,$resp_port,$stime,$sftime));

            $packets=array();
            while ($row3 = pg_fetch_row($result3))
            {
                $packets[]=$row3[0];
            }

            $found_packets=count($packets);
            $total_pkts_fnd=$total_pkts_fnd+$found_packets;
            $expected_packets=$orig_packets+$resp_packets;
            $pkt_fnd_ratio=$found_packets/$expected_packets;

            //pg_query($conn,"BEGIN WORK");  // this did not *seem* to be faster (but needs more testing)
            foreach($packets as $packet)
            {
                $result4=pg_execute($conn,"tagpackets",array($uid,$packet));
                if(!$result4)
                {
                    $now=time();
                    fwrite($logfile,date('m-d-Y-G:i:s',$now)." ERROR processing UID: ".$uid." pid:".$packet."\n");
                }
            }
            //pg_query($conn,"COMMIT WORK");

            $result5=pg_execute($conn,"updatesession", array($found_packets,$expected_packets,$pkt_fnd_ratio,TRUE,$uid));
            if(!$result5)
            {
                $now=time();
                fwrite($logfile,date('m-d-Y-G:i:s',$now)." ERROR unable to update verified uid: ".$uid."\n");
                fwrite($logfile,date('m-d-Y-G:i:s',$now)." found_packets:".$found_packets." expected_packets:"
                    .$expected_packets." pkt_fnd_ratio:".$pkt_fnd_ratio."\n");

            }
        }


        if($results7)
        {
            $now = time();
            fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file.": tagged packets, session_count=".$session_count."\n");
        }
        $count++;
        $time_finish = microtime(true);
        $total_time = $time_finish - $time_start;
        syslog(LOG_INFO, "file=".$file." seconds=".$total_time." total_pkts_fnd=".$total_pkts_fnd." sessions_found="
            .$session_count."\n");
    }

}
$now = time();
fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file.": Exiting\n");

fclose($logfile);
pg_close($conn);
closelog();


?>
