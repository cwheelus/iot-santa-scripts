-- iot1 schema
DROP TABLE handsets;

CREATE TABLE handsets (
  entryid     BIGSERIAL PRIMARY KEY UNIQUE,
  entry       TIMESTAMP,
  ip          INET
);


DROP TABLE samples;

CREATE TABLE samples (
  sampleid          BIGSERIAL PRIMARY KEY UNIQUE,   -- generated
  source            VARCHAR default 'anon',          -- source of data
  filename          VARCHAR UNIQUE,                 -- pcap filename
  filesize          INTEGER,                        -- pcap filesize in bytes
  skip              BOOLEAN DEFAULT 'False',        -- skip this sample
  bro_proc          timestamp,                      -- time successfully processed by bro                (step 1)
  tshark_proc       timestamp,                      -- time successfully processed by tshark pass 1      (step 2)
  pushed_packets    TIMESTAMP,                      -- time packets pushed                               (step 3)
  --tsharkpass2_proc  timestamp,                    -- time successfully processed by tshark pass 2      (step 4 - deprecated)
  pushed_sessions   TIMESTAMP,                      -- time sessions pushed                              (step 5)
  tagged_packets    timestamp,                      -- time packets tagged                               (step 6)
  features_added    TIMESTAMP                       -- time features calculated                          (step 7)
);


DROP TABLE p;

-- packet capture table
CREATE TABLE p (
   pid              BIGSERIAL PRIMARY KEY UNIQUE,   -- generated
   seq              BIGINT NOT NULL,                -- number of packet in sample - tshark field: frame.number
   source           VARCHAR NOT NULL,               -- data source
   filename         VARCHAR NOT NULL,               -- name of file this packet came from
   ptime            TIMESTAMP NOT NULL,             -- timestamp of packet tshark field: frame.time_epoch
   src_ip_address   INET NOT NULL,                  -- tshark field: ip.src
   dst_ip_address   INET NOT NULL,                  -- tshark field: ip.dst
   frame_protos     VARCHAR,                        -- tshark field: frame.protocols
   src_port         INTEGER,                        -- tshark field: udp.srcport | tcp.srcport | sctp.srcport
   dst_port         INTEGER,                        -- tshark field: udp.dstport | tcp.dstport | sctp.dstport
   bytes            INTEGER NOT NULL,               -- tshark field: ip.len
   info             VARCHAR,                        -- tshark field: expert.message (& other)
   uid              VARCHAR
);



DROP TABLE s;

-- session table
CREATE TABLE s (
  uid              VARCHAR PRIMARY KEY UNIQUE,
  source           VARCHAR NOT NULL,
  filename         VARCHAR NOT NULL,
  stime            TIMESTAMP NOT NULL,
  orig_ip          INET NOT NULL,
  resp_ip          INET NOT NULL,
  orig_port        INTEGER,
  resp_port        INTEGER,
  proto            VARCHAR,
  service          VARCHAR,
  label            VARCHAR DEFAULT 'unknown',
  io_match         BOOLEAN DEFAULT 'False',
  duration         NUMERIC,
  conn_state       VARCHAR,
  local_orig       BOOLEAN,
  local_resp       BOOLEAN,
  missed_bytes     INTEGER,
  history          VARCHAR,
  orig_bytes       INTEGER,
  orig_packets     INTEGER,
  resp_bytes       INTEGER,
  resp_packets     INTEGER,
  tunnel_parents   VARCHAR,
  in_conv          NUMERIC,
  out_conv         NUMERIC,
  repetition       NUMERIC,   --delete
  periodicity      NUMERIC,   --delete
  invel_pps        NUMERIC,
  invel_bps        NUMERIC,
  invel_Bpp        NUMERIC,
  outvel_pps       NUMERIC,
  outvel_bps       NUMERIC,
  outvel_Bpp       NUMERIC,
  out_rep				   NUMERIC,
  out_prdcty			 NUMERIC,
  in_rep					 NUMERIC,
  in_prdcty        NUMERIC,
  riotp            NUMERIC,
  riotb            NUMERIC,
  darkaccess       BOOLEAN DEFAULT 'False',
  port_scan        BOOLEAN DEFAULT 'False',
  dark_scan        BOOLEAN DEFAULT 'False',
  multiport        BOOLEAN DEFAULT 'False',
  wasunknown       BOOLEAN DEFAULT 'False',
  origasn          INTEGER,
  origasn_anon     INTEGER,
  ASN_scan         BOOLEAN DEFAULT 'False',
  ignore           BOOLEAN DEFAULT 'FALSE',
  verified         BOOLEAN DEFAULT 'FALSE',
  flags            VARCHAR,
  exp_packets      INTEGER,
  found_packets    INTEGER,
  inbndpkts        INTEGER,
  outbndpkts       INTEGER,
  pktfnd_ratio     NUMERIC,
  ipclass          varchar,
  sstime           TIMESTAMP

);


DROP TABLE internal_addr;

-- Define network context  (inbound/outbound)
CREATE TABLE internal_addr (
  ip_range        CIDR    PRIMARY KEY UNIQUE
);

INSERT into internal_addr VALUES ('11.22.33.4/32');
INSERT into internal_addr VALUES ('11.22.33.5/32');

drop table import_errors;

create table import_errors
(
  errordate TIMESTAMP DEFAULT now(),
  filename  VARCHAR,
  linenum   INTEGER,
  colname   VARCHAR,
  error     VARCHAR,
  value     VARCHAR,
  ignore    BOOLEAN DEFAULT 'FALSE'
);


create index on p (ptime);
create index on p (src_ip_address);
create index on p (dst_ip_address);
create index on p (src_port);
create index on p (dst_port);
create index on p (uid);
create index on p (pid);
create index on p (filename);
