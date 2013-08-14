<?php

/*
 * Amazon AWS Access Key Identifiers (Public and Secret Keys) as well as associate tag
 *
 * Become associate and get your AccessKey Id from http://docs.aws.amazon.com/AWSECommerceService/2011-08-01/DG/becomingAssociate.html
 * Then register your AccessKey Id for the Product Advertising API https://affiliate-program.amazon.com/gp/flex/advertising/api/sign-in.html
 *
 * Read through the TOS 
 */

define('AWS_API_KEY', '');
define('AWS_API_SECRET_KEY', '');
define('AWS_ASSOCIATE_TAG', '');

/*
 * @var array specifies a list of Amazon Standard Identification Numbers (ASINs)
 *
 * example for digital humanities:
 * $asins = array("262018470","230292658","1405168064","816677956","1856047660","472051989","226321428","252078209","26251740","26212176","1409410684"); 
 *
 * example for electronic literature:
 * $asins = array("801842816","801855853","801882575","801855799","816667381","268030855","262517531","262631873","262633183","1441115919","1441107452");
 */

$asins = array();

/*
 * @var int specifies the crawl depth
 */
$crawldepth = 1;

/*
 * Specify locales
 *
 * @var array specifies list of possible locales
 * @link http://docs.aws.amazon.com/AWSECommerceService/latest/DG/Welcome.html
 * Possible values: "ca", "cn", "de", "es",  "fr", "it", "co.jp", "co.uk", "com"
 */
$locales = array("com");

/*
 * @var string can be used to prepend the filename with a custom string
 */
$startFileNameWith = "amazon";

/*
 * @var bool $combined 
 * 
 * if set to FALSE one network file per ASIN will be produced. If set to TRUE one network which combines them all will be produced
 */
$combined = false;

date_default_timezone_set('Europe/Amsterdam');

?>