-- The dev site database (`wordpress`) is created by the image; this adds the
-- database used by the PHPUnit suite.
CREATE DATABASE IF NOT EXISTS wordpress_test;
GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wordpress'@'%';
