#!/usr/bin/php

<?php
/**
 * calc-features.php - #6 after tag-packets.php - next: push-handset, label-data, build-arff
 *
 * NOTE: DO NOT RUN WHILE tag_packets.php IS RUNNING
 *
 * Created by: Charles Wheelus  -  5/9/17
 */


/***********************************************************************************************************************
 * function: calc_pps
 ***********************************************************************************************************************/
function calc_pps($num_packets,$interval)
{
    if($interval>0)
        return $num_packets/$interval;
    else
        return 1;
}

/***********************************************************************************************************************
 * function: calc_Bps
 ***********************************************************************************************************************/
function calc_bps($bytes,$interval)
{
    if($interval>0)
        return $bytes/$interval;
    else
        return $bytes;
}

/***********************************************************************************************************************
 * function: calc_Bpp
 ***********************************************************************************************************************/
function calc_bpp($bytes,$num_packets)
{
    if($num_packets>0)
        return $bytes/$num_packets;
    else
        return PHP_INT_MAX; // This should never happen since this is only called when $num_packets > 0   :)
}

/***********************************************************************************************************************
 * function: calc_convergence
 ***********************************************************************************************************************/
function calc_convergence($packets)
{
    $conv=NULL;

    $packet_size=array();

    foreach($packets as $packet)
    {
        $packet_size[]=$packet[2];
    }
    if(count($packet_size)>1)
        $conv=calc_variance($packet_size);

    return $conv;
}

/***********************************************************************************************************************
 * function: calc_timediff
 ***********************************************************************************************************************/
function calc_timediff($first,$second)
{
    $one=date_parse($first);
    $two=date_parse($second);


    if($one['error_count'] > 0)
    {
        print("ERROR: ");
        print_r($one['errors']);
        print("\n\n");
    }
    if($one['warning_count'] > 0)
    {
        print("WARNING: ");
        print_r($one['warnings']);
        print("\n\n");
    }
    if($two['error_count'] > 0)
    {
        print("ERROR: ");
        print_r($two['errors']);
        print("\n\n");
    }
    if($two['warning_count'] > 0)
    {
        print("WARNING: ");
        print_r($two['warnings']);
        print("\n\n");
    }


    $one_ts=mktime($one['hour'],$one['minute'],$one['second'],$one['month'],$one['day'],$one['year']);
    $two_ts=mktime($two['hour'],$two['minute'],$two['second'],$two['month'],$two['day'],$two['year']);
    $diff= $two_ts - $one_ts + $two['fraction'] - $one['fraction'];;

    return (real)$diff;
}

/***********************************************************************************************************************
 * function: calc_periodicity
 ***********************************************************************************************************************/
function calc_periodicity($packets)
{
    $periodicity=NULL;

    foreach($packets as $packet)
    {
        $ptime[]=$packet[1];                        // save timestamp to array
    }
    $num_packets=count($ptime);
    for($i=1;$i<$num_packets;$i++)                  // calculate the difference between each timestamp
    {
        $pdiff[]=calc_timediff($ptime[$i-1],$ptime[$i]);
    }
    if($num_packets>1)
        $periodicity=(calc_variance($pdiff));       // periodicity = the variance in inter-arrival time of each packet


    return $periodicity;
}

/***********************************************************************************************************************
 * function: calc_repetition
 ***********************************************************************************************************************/
function calc_repetition($packets)
{
    //print_r($packets);

    $count=0;
    $pcount=0;

    foreach($packets as $packet)
    {
        $packet_size[]=$packet[2];
        $pcount++;
    }

    //print_r($packet_size);
    $values = array_count_values($packet_size);
    $mode = array_search(max($values), $values);
    //print("mode: ".$mode."\n");
    foreach($packet_size as $size)
    {
        if($size==$mode) $count++;
    }

    if($count==0) $pcount=1;

    //print("count: ".$count." pcount: ".$pcount." rep: ".$count/$pcount." \n\n");
    //exit;
    return $count/$pcount;
}

/***********************************************************************************************************************
 * function: calc_variance
 *
 * $samples is an array of numbers
 *
 ***********************************************************************************************************************/
function calc_variance($samples)
{

    $num_samples = count($samples);                 // count: number of samples

    if ($num_samples < 2)
    {
        //print("ERROR: at least two numbers required to calculate variance\n");
        return NULL;
    }
    $average=array_sum($samples)/$num_samples;      // calculate average value of samples

    foreach ($samples as $sample)                   // for each sample...
    {                                               // make an array of
        $temp_array[]=pow($sample-$average,2);      // (sample-average)^2
    }

    $sum=array_sum($temp_array);                    // sum all of these

    $variance=$sum/($num_samples-1);                // variance = (sum of all samples) / (number of samples - 1)

    return $variance;
}

/***********************************************************************************************************************
 * function: calc_riot
 *
 * calculate the ratio of inbound to outbound traffic (for both bytes and packet count)
 ***********************************************************************************************************************/
function calc_riot($inbound,$outbound)
{

    if($outbound==0)
    {
        return PHP_INT_MAX;
    }
    else

        return $inbound/$outbound;

}

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

$time_start = microtime(true);
openlog("crfeat", LOG_PID | LOG_PERROR, LOG_LOCAL1);
require('./project_settings.php');
$conn = pg_connect(CON);

$logfile=fopen(LOGDIR.'calc-features.log','a+');

chdir(DATADIR);

/***********************************************************************************************************************
 * prepared statements for postgresql
 **********************************************************************************************************************/

$prepare1 = pg_prepare($conn, "findsessions",
    "select uid,filename,stime,duration,orig_ip,resp_ip,orig_packets,resp_packets,orig_bytes,resp_bytes,orig_port,
    resp_port from s where filename=$1 AND ignore='f' AND verified='t'"
);

$prepare2 = pg_prepare($conn, "getpackets",
    "select pid,ptime,bytes,frame_protos,src_ip_address,dst_ip_address,uid,filename from p where filename=$1 AND
    (src_ip_address=$2 AND dst_ip_address=$3 AND src_port=$4 AND dst_port=$5) AND ptime>=$6 AND ptime<=$7 AND uid=$8"
);

$prepare3 = pg_prepare($conn, "addfeatures",
    "UPDATE s SET riotb=$1, riotp=$2, outvel_bps=$3, outvel_pps=$4, outvel_bpp=$5, out_conv=$6, out_rep=$7,
    out_prdcty=$8, invel_bps=$9, invel_pps=$10, invel_bpp=$11, in_conv=$12, in_rep=$13, in_prdcty=$14, inbndpkts=$15,
    outbndpkts=$16 WHERE uid=$17"
);

$query1="SELECT filename FROM samples WHERE (tagged_packets IS NOT NULL) AND (features_added IS NULL) "
    ." AND (skip='FALSE') LIMIT ".CFEATLIMIT;
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

$filecount=0;
foreach($files as $file)
{

    $result1=pg_execute($conn,"findsessions",array($file));

    $count=1;
    while ($row = pg_fetch_row($result1))
    {
        //print($count." ");
        $outbound_packets=array();
        $inbound_packets=array();

        //if($iteration>1) exit;
        if(DEBUG) print_r($row);
        $uid=$row[0];
        $filename=$row[1];
        $stime=$row[2];
        $duration=$row[3];
        $orig_ip=$row[4];
        $resp_ip=$row[5];
        $orig_packets=$row[6];
        $resp_packets=$row[7];
        $orig_bytes=$row[8];
        $resp_bytes=$row[9];
        $orig_port=$row[10];
        $resp_port=$row[11];
        $sftime=add_duration($stime,$duration);

        $riotp=calc_riot($orig_packets,$resp_packets);
        $riotb=calc_riot($orig_bytes,$resp_bytes);

        if($orig_packets>0)  // if there are outbound packets
        {
            $outbound=pg_execute($conn,"getpackets",array($file,$orig_ip,$resp_ip,$orig_port,$resp_port,$stime,$sftime,$uid));
            if(DEBUG) print("outbound packets:\n");

            while ($row2 = pg_fetch_row($outbound))
            {
                if(DEBUG) print_r($row2);
                $outbound_packets[]=$row2;
            }
            if(DEBUG) print("outbound_packets=".count($outbound_packets)."\n");
            $outbndpkts=count($outbound_packets);
            if($outbndpkts>2)
            {
                $outperiod=calc_periodicity($outbound_packets);
                $outconv=calc_convergence($outbound_packets);
                $outrep=calc_repetition($outbound_packets);
            }
            elseif($outbndpkts>=1)
            {
                $outconv=calc_convergence($outbound_packets);
                $outrep=calc_repetition($outbound_packets);
                $outperiod=NULL;
            }
            else
            {
                $outconv=NULL;
                $outrep=NULL;
                $outperiod=NULL;    
            }
            $outvelpps=calc_pps($orig_packets,$duration);
            $outvelbps=calc_bps($orig_bytes,$duration);
            $outvelbpp=calc_bpp($orig_bytes,$orig_packets);
        }
        else
        {
            $outbndpkts=0;
            $now=time();
            fwrite($logfile,date('m-d-Y-G:i:s',$now)." no outbound packets found: ".$uid."\n");
        }

        if($resp_packets>0)  // if there are inbound packets
        {
            $inbound=pg_execute($conn,"getpackets",array($file,$resp_ip,$orig_ip,$resp_port,$orig_port,$stime,$sftime,$uid));
            if (DEBUG) print("inbound packets:\n");

            while ($row3 = pg_fetch_row($inbound))
            {
                if (DEBUG) print_r($row3);
                $inbound_packets[]=$row3;

            }
            if(DEBUG) print("inbound_packets=".count($inbound_packets)."\n");
            $inbndpkts=count($inbound_packets);


            if($inbndpkts>2)
            {
                $inperiod=calc_periodicity($inbound_packets);
                $inconv=calc_convergence($inbound_packets);
                $inrep=calc_repetition($inbound_packets);
            }
            elseif($inbndpkts>=1)
            {
                $inconv=calc_convergence($inbound_packets);
                $inrep=calc_repetition($inbound_packets);
                $inperiod=NULL;
            }
            else
            {
                $inconv=NULL;
                $inrep=NULL;
                $inperiod=NULL;
            }
            $invelpps=calc_pps($resp_packets,$duration);
            $invelbps=calc_bps($resp_bytes,$duration);
            $invelbpp=calc_bpp($resp_bytes,$resp_packets);
        }
        else
        {
            $inbndpkts=0;
            $now=time();
            fwrite($logfile,date('m-d-Y-G:i:s',$now)." no inbound packets found: ".$uid."\n");
        }

        if(strlen($outvelbps)==0)   $outvelbps=NULL;
        if(strlen($outvelpps)==0)   $outvelpps=NULL;
        if(strlen($outvelbpp)==0)   $outvelbpp=NULL;
        if(strlen($outconv)==0)     $outconv=NULL;
        if(strlen($outrep)==0)      $outrep=NULL;
        if(strlen($outperiod)==0)   $outperiod=NULL;
        if(strlen($invelbps)==0)    $invelbps=NULL;
        if(strlen($invelpps)==0)    $invelpps=NULL;
        if(strlen($invelbpp)==0)    $invelbpp=NULL;
        if(strlen($inconv)==0)      $inconv=NULL;
        if(strlen($inrep)==0)       $inrep=NULL;
        if(strlen($inperiod)==0)    $inperiod=NULL;

        $result5=pg_execute($conn,"addfeatures",array($riotb,$riotp,$outvelbps,$outvelpps,$outvelbpp,$outconv,$outrep,
            $outperiod,$invelbps,$invelpps,$invelbpp,$inconv,$inrep,$inperiod,$inbndpkts,$outbndpkts,$uid));
        if(!$result5)
        {
            $now=time();
            fwrite($logfile,date('m-d-Y-G:i:s',$now)." error writing attributes to session - uid: ".$uid."\n");
        }

        $count++;

    }
    $now=time();
    $query7="UPDATE samples SET features_added = '".date('m-d-Y G:i:s',$now)."' where filename='".$file."'";
    $results7=pg_query($conn,$query7);
    if($results7)
    {
        $now = time();
        fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file.": features added for ".$file." - processed ".$count." sessions\n");
    }
    $filecount++;

}

fclose($logfile);
pg_close($conn);
$time_finish = microtime(true);
$total_time = $time_finish - $time_start;

syslog(LOG_INFO, "files=".$filecount." seconds=".$total_time." limit=".CFEATLIMIT."\n");
closelog();

?>
