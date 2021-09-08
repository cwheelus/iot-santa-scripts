# iot-santa-scripts
Scripts for transforming raw packet capture ("pcap") files to machine learning training data sets

To facilitate the verification of the methodologies used in various academic papers, as well as illustrate the transformation process of the data from raw pcap to training dataset, including the calculation of various attributes; I am releasing the source code used to produce the datasets for my Ph.D. research over the period of 2013-2020.  Please note that a good portion of the codebase was created in the wee hours of my timezone under the heavy influence of espresso and/or bourbon.  No warranty of any kind is expressed or implied. Any user of these scripts or derivative works assumes all risk.  No guarantee of correctness, suitability or fitness for any purpose is expressed herein.

Further the code is ugly.  If you are a well-trained and highly skilled developer, it is recommended that you keep an airline vomit bag handy.  I was once told by a very talented developer that my code looked like a math professor wrote it.  I chose to receive this as a compliment though he did confirm that he had intended for his comment to be pejorative in nature.

Additionally, it is written in PHP.  Yes, I know you probably do not like PHP and may even decide to question my suitability as a data scientist because I didn't use python.  I really do not care and any flame mail you might care to send may be sent to: deaththreats@whitehouse.gov.

The scripts contemplate the usage of a PostgreSQL backend database for ETL, and the processing of raw pcap files by Zeek (fka "Bro") and Tshark (included with Wireshark)

There is a fair amount of cruft included.  I have a heavy workload at the moment, so I have no time to clean it up and make it pretty.  

It is what it is. 

I have taken the time to change private information that is not for public consumption.  Don't worry, you can still figure it out. I believe in you. So does your mom/dog/partner/imaginary-friend.  You can do it!

The following files are included:

•	iot1.sql - schema for building the necessary PostgreSQL tables, indexes, etc.

•	run-bro.php - Step 1 - process pcaps with Zeek (it was still called bro when I began this project) - output files to a directory

•	run-tshark.php - Step 2 - process pcaps with Tshark - output file to the same directory as zeek did

•	push-packets.php - Step 3 - push packets into PostgreSQL

•	push-sessions.php - Step 4 - push sessions into PostgreSQL

•	tag-packets.php - Step 5 - reconcile packets with sessions

•	calc-features.php - Step 6 - calculate features needed for training models by means of machine learning

•	project-settings.php - used to set project wide data

From here, the poor souls that have made it this far will have to output the sessions table into whatever format they need for their choice of machine learning platform.  If you are c̵r̵a̵z̵y̵ ̵e̵n̵o̵u̵g̵h̵ motivated to do this, you should probably know what to do from this point.

Feel free to reach out to me with questions, but it may take some time to get back to you as my schedule is pretty demanding these days, and I do not check this site very often.  It would be better to find me on Linkedin and drop me a line.
