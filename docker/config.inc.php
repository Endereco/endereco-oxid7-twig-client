<?php

$this->dbType = 'pdo_mysql';
$this->dbHost = getenv('MYSQL_HOST') ?: 'db';
$this->dbPort = 3306;
$this->dbName = getenv('MYSQL_DATABASE') ?: 'db';
$this->dbUser = getenv('MYSQL_USER') ?: 'user';
$this->dbPwd  = getenv('MYSQL_PASSWORD') ?: 'pwd';

$this->sShopURL    = getenv('SHOP_URL') ?: 'http://localhost/';
$this->sSSLShopURL = getenv('SHOP_URL') ?: 'http://localhost/';

$this->sShopDir    = '/var/www/html/source/';
$this->sCompileDir = '/var/www/html/source/tmp/';

$this->iUtfMode = 1;
$this->iDebug   = 0;
