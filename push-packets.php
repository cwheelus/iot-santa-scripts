#!/usr/bin/php

<?php
/**
 * push-packets.php - #3 after run-tshark.php - next: push-sessions.php
 *
 * Created by: Charles Wheelus  -  5/3/17
 */

$time_start = microtime(true);

openlog("pktpush", LOG_PID | LOG_PERROR, LOG_LOCAL1);

require('./project_settings.php');

$conn = pg_connect(CON);

$now=time();
$logfile=fopen(LOGDIR.'push-packets.log','a+');

/***********************************************************************************************************************
 * prepared statements for postgresql
 **********************************************************************************************************************/

$prepare1 = pg_prepare($conn, "pushpackets",
    "INSERT INTO p (source,filename,seq,ptime,src_ip_address,dst_ip_address,src_port,dst_port,frame_protos,bytes,info)
  VALUES ($1,$2,$3,to_timestamp($4),$5,$6,$7,$8,$9,$10,$11)");

$prepare2 = pg_prepare($conn, "pushpacketsNSP",
    "INSERT INTO p (source,filename,seq,ptime,src_ip_address,dst_ip_address,dst_port,frame_protos,bytes,info)
  VALUES ($1,$2,$3,to_timestamp($4),$5,$6,$7,$8,$9,$10)");

$prepare3 = pg_prepare($conn, "pushpacketsNDP",
    "INSERT INTO p (source,filename,seq,ptime,src_ip_address,dst_ip_address,src_port,frame_protos,bytes,info)
  VALUES ($1,$2,$3,to_timestamp($4),$5,$6,$7,$8,$9,$10)");

$prepare4 = pg_prepare($conn, "pushpacketsNP",
    "INSERT INTO p (source,filename,seq,ptime,src_ip_address,dst_ip_address,frame_protos,bytes,info)
  VALUES ($1,$2,$3,to_timestamp($4),$5,$6,$7,$8,$9)");

chdir(DATADIR);

$query1="SELECT filename FROM samples WHERE (tshark_proc IS NOT NULL) AND (pushed_packets IS NULL) AND (skip='FALSE') LIMIT ".PUSHPLIMIT;
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

//if(DEBUG) print_r($files);

$count=0;
foreach($files as $file)
{
    //print($file."\n");
    if(chdir(WORKDIR.$file))
    {

        $now=time();
        fwrite($logfile,date('m-d-Y-G:i:s',$now).' '.$file." - \n");

        $fp = fopen('packet.txt', 'r');
        $linenum=0;
        while ( !feof($fp) )
        {
            $line = fgets($fp);
            $linenum++;
            $first_char=substr($line,0,1);
            if(strcmp($first_char,'#')==0) continue;
            elseif(strlen($line)==0) continue;
            else
            {

                $line=ltrim($line);
                $line = preg_replace('!\s+!', ' ', $line);  // get rid of extra whitespace in each line
                $columns1=explode(",",$line);
                //print_r($columns1);

                /* /usr/sbin/tshark -r <file> -t e -T fields -e frame.number -e frame.time_epoch -e frame.protocols
                    -e ip.src -e ip.dst -e udp.srcport -e tcp.srcport -e sctp.srcport -e udp.dstport -e tcp.dstport
                    -e sctp.dstport -e ip.len -e expert.message
                */

                $seq=$columns1[0];
                $ptime=$columns1[1];
                $frame_protos=$columns1[2];
                $src_ip=$columns1[3];
                $dst_ip=$columns1[4];
                if(strcmp($columns1[6],'')===0)
                {
                    if(strcmp($columns1[5],'')===0)
                    {
                        $src_port=$columns1[7];
                    }
                    else $src_port=$columns1[5];
                }
                else $src_port=$columns1[6];
                if(strcmp($columns1[9],'')===0)
                {
                    if(strcmp($columns1[8],'')===0)
                    {
                        $dst_port=$columns1[10];
                    }
                    else $dst_port=$columns1[8];
                }
                else $dst_port=$columns1[9];

                $bytes=$columns1[11];
                $info=$columns1[12];
                $sourcename=PROJECT;

                if(strcmp($dst_port,'')===0)   // dst_port IS NULL
                {
                    if(strcmp($src_port,'')===0)  // AND src_port IS NULL
                    {
                        if (!pg_connection_busy($conn))
                        {
                            pg_send_execute($conn, "pushpacketsNP",
                                array(
                                    $sourcename,$file,$seq,$ptime,$src_ip,$dst_ip,$frame_protos,$bytes,$info
                                ));
                            $res2 = pg_get_result($conn);
                            $error=pg_last_error($conn);

                            if($error)
                            {
                                $now = time();
                                fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file." ".$error." \n");
                                fwrite($logfile, date('m-d-Y-G:i:s', $now)." ptime=".$ptime." src_ip=".$src_ip." dst_ip=".$dst_ip
                                    ." src_port=".$src_port." dst_port=".$dst_port." frame_protos=".$frame_protos." bytes=".$bytes
                                    ." info=".$info."\n");
                            }

                        }
                        else
                        {
                            print("Unable to update!....trying again in one second\n");
                            sleep(1);
                            if (!pg_connection_busy($conn))
                            {
                                pg_send_execute($conn, "pushpacketsNP",
                                    array(
                                        $sourcename,$file,$seq,$ptime,$src_ip,$dst_ip,$frame_protos,$bytes,$info
                                    ));
                                $res2 = pg_get_result($conn);
                                $error=pg_last_error($conn);

                                if($error)
                                {
                                    $now = time();
                                    fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file." ".$error." \n");
                                    fwrite($logfile, date('m-d-Y-G:i:s', $now)." ptime=".$ptime." src_ip=".$src_ip." dst_ip=".$dst_ip
                                        ." src_port=".$src_port." dst_port=".$dst_port." frame_protos=".$frame_protos." bytes=".$bytes
                                        ." info=".$info."\n");
                                }
                            }
                            else
                            {
                                print("Unable to update AGAIN! (wah wah WAH!)\n");
                            }
                        }
                    }
                    else // AND src_port IS NOT NULL
                    {
                        if (!pg_connection_busy($conn))
                        {
                            pg_send_execute($conn, "pushpacketsNDP",
                                array(
                                    $sourcename,$file,$seq,$ptime,$src_ip,$dst_ip,$src_port,$frame_protos,$bytes,$info
                                ));
                            $res2 = pg_get_result($conn);
                            $error=pg_last_error($conn);

                            if($error)
                            {
                                $now = time();
                                fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file." ".$error." \n");
                                fwrite($logfile, date('m-d-Y-G:i:s', $now)." ptime=".$ptime." src_ip=".$src_ip." dst_ip=".$dst_ip
                                    ." src_port=".$src_port." dst_port=".$dst_port." frame_protos=".$frame_protos." bytes=".$bytes
                                    ." info=".$info."\n");
                            }

                        }
                        else
                        {
                            print("Unable to update!....trying again in one second\n");
                            sleep(1);
                            if (!pg_connection_busy($conn))
                            {
                                pg_send_execute($conn, "pushpacketsNDP",
                                    array(
                                        $sourcename,$file,$seq,$ptime,$src_ip,$dst_ip,$src_port,$frame_protos,$bytes,$info
                                    ));
                                $res2 = pg_get_result($conn);
                                $error=pg_last_error($conn);

                                if($error)
                                {
                                    $now = time();
                                    fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file." ".$error." \n");
                                    fwrite($logfile, date('m-d-Y-G:i:s', $now)." ptime=".$ptime." src_ip=".$src_ip." dst_ip=".$dst_ip
                                        ." src_port=".$src_port." dst_port=".$dst_port." frame_protos=".$frame_protos." bytes=".$bytes
                                        ." info=".$info."\n");
                                }
                            }
                            else
                            {
                                print("Unable to update AGAIN! (wah wah WAH!)\n");
                            }
                        }
                    }
                }
                elseif(strcmp($src_port,'')===0) // src_port IS NULL (dst_port MUST NOT BE NULL since we already checked above)
                {
                    if (!pg_connection_busy($conn))
                    {
                        pg_send_execute($conn, "pushpacketsNSP",
                            array(
                                $sourcename,$file,$seq,$ptime,$src_ip,$dst_ip,$dst_port,$frame_protos,$bytes,$info
                            ));
                        $res2 = pg_get_result($conn);
                        $error=pg_last_error($conn);

                        if($error)
                        {
                            $now = time();
                            fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file." ".$error." \n");
                            fwrite($logfile, date('m-d-Y-G:i:s', $now)." ptime=".$ptime." src_ip=".$src_ip." dst_ip=".$dst_ip
                                ." src_port=".$src_port." dst_port=".$dst_port." frame_protos=".$frame_protos." bytes=".$bytes
                                ." info=".$info."\n");
                        }

                    }
                    else
                    {
                        print("Unable to update!....trying again in one second\n");
                        sleep(1);
                        if (!pg_connection_busy($conn))
                        {
                            pg_send_execute($conn, "pushpacketsNSP",
                                array(
                                    $sourcename,$file,$seq,$ptime,$src_ip,$dst_ip,$dst_port,$frame_protos,$bytes,$info
                                ));
                            $res2 = pg_get_result($conn);
                            $error=pg_last_error($conn);

                            if($error)
                            {
                                $now = time();
                                fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file." ".$error." \n");
                                fwrite($logfile, date('m-d-Y-G:i:s', $now)." ptime=".$ptime." src_ip=".$src_ip." dst_ip=".$dst_ip
                                    ." src_port=".$src_port." dst_port=".$dst_port." frame_protos=".$frame_protos." bytes=".$bytes
                                    ." info=".$info."\n");
                            }
                        }
                        else
                        {
                            print("Unable to update AGAIN! (wah wah WAH!)\n");
                        }
                    }
                }
                else  // neither src_port nor dst_port is NULL
                {
                    if (!pg_connection_busy($conn))
                    {
                        pg_send_execute($conn, "pushpackets",
                            array(
                                $sourcename,$file,$seq,$ptime,$src_ip,$dst_ip,$src_port,$dst_port,$frame_protos,$bytes,$info
                            ));
                        $res2 = pg_get_result($conn);
                        $error=pg_last_error($conn);

                        if($error)
                        {
                            $now = time();
                            fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file." ".$error." \n");
                            fwrite($logfile, date('m-d-Y-G:i:s', $now)." ptime=".$ptime." src_ip=".$src_ip." dst_ip=".$dst_ip
                                ." src_port=".$src_port." dst_port=".$dst_port." frame_protos=".$frame_protos." bytes=".$bytes
                                ." info=".$info."\n");
                        }

                    }
                    else
                    {
                        print("Unable to update!....trying again in one second\n");
                        sleep(1);
                        if (!pg_connection_busy($conn))
                        {
                            pg_send_execute($conn, "pushpackets",
                                array(
                                    $sourcename,$file,$seq,$ptime,$src_ip,$dst_ip,$src_port,$dst_port,$frame_protos,$bytes,$info
                                ));
                            $res2 = pg_get_result($conn);
                            $error=pg_last_error($conn);

                            if($error)
                            {
                                $now = time();
                                fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file." ".$error." \n");
                                fwrite($logfile, date('m-d-Y-G:i:s', $now)." ptime=".$ptime." src_ip=".$src_ip." dst_ip=".$dst_ip
                                    ." src_port=".$src_port." dst_port=".$dst_port." frame_protos=".$frame_protos." bytes=".$bytes
                                    ." info=".$info."\n");
                            }
                        }
                        else
                        {
                            print("Unable to update AGAIN! (wah wah WAH!)\n");
                        }
                    }


                }

            }
        }
        fclose($fp);
        $now=time();
        $query2="UPDATE samples SET pushed_packets = '".date('m-d-Y G:i:s',$now)."' where filename='".$file."'";
        $results2=pg_query($conn,$query2);
        if($results2)
        {
            $now = time();
            fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file.": pushed packets into db \n");
        }
    }
    else
    {
        $now=time();
        fwrite($logfile,date('m-d-Y-G:i:s',$now)." Unable to change directory: ".$file."\n");
        continue;
    }

    $count++;
}


fclose($logfile);
pg_close($conn);

$time_finish = microtime(true);
$total_time = $time_finish - $time_start;

syslog(LOG_INFO, "files=".$count." seconds=".$total_time." limit=".PUSHPLIMIT."\n");
closelog();

?>
