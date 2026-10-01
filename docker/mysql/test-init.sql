-- The test MySQL hosts two databases so the PHP and Go suites can run
-- independently of each other.
CREATE DATABASE IF NOT EXISTS merchant_flow_go_test;
GRANT ALL PRIVILEGES ON merchant_flow_go_test.* TO 'merchant_flow'@'%';
