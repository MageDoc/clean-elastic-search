<?php

//require_once('vendor/autoload.php');

define('MAGENTO_ROOT', getcwd());

$mageFilename = MAGENTO_ROOT . '/app/Mage.php';
$maintenanceFile = 'maintenance.flag';

if (!file_exists($mageFilename)) {
    exit;
}

if (file_exists($maintenanceFile)) {
    include_once dirname(__FILE__) . '/errors/503.php';
    exit;
}

require_once $mageFilename;

Mage::register('custom_entry_point', true);
Mage::$headersSentThrowsException = false;
Mage::init('admin');

$session = Mage::getSingleton('core/session', array('name' => Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE))->start();
if (!Mage::getSingleton('admin/session')->isLoggedIn()) {
    die;
}

function checkAccess()
{
    $xml = new SimpleXMLElement(file_get_contents('app/etc/local.xml'));
    if (!isset($xml->global->elasticsearch->secret)) {
        throw new Exception("Missing elasticsearch secret config in local.xml");
    }

    if (!isset($_GET['key'])) {
        throw new Exception("Missing or invalid key");
    }

    if ($_GET['key'] != (string)$xml->global->elasticsearch->secret) {
        throw new Exception("Missing or invalid key");
    }

    return true;
}

/**
 * @return Elastica\Index
 * @throws Exception
 */
function getElasticaIndex()
{
    $xml = new SimpleXMLElement(file_get_contents('app/etc/local.xml'));
    if (!isset($xml->global->elasticsearch)) {
        throw new Exception("Missing elasticsearch config in local.xml");
    }

    if (! (string)$xml->global->elasticsearch->index) {
        throw new Exception("Missing elasticsearch index name in local.xml");
    }

    /* var $elasticaClient Elastica\Client */
    $elasticaClient = new \Elastica\Client(array(
        'host' => (string)$xml->global->elasticsearch->host,
        'port' => (string)$xml->global->elasticsearch->port,
    ));

    $index = $elasticaClient->getIndex((string)$xml->global->elasticsearch->index);

    return $index;
}

/**
 * @param $result \Elastica\Result
 */
function getResultUrl($result)
{
    $baseUrl = $_GET['base_url'];
    $data = $result->getData();
    $type = $result->getType();

    if ($type == 'customer') {
	    return Mage::helper('adminhtml')->getUrl('adminhtml/customer/edit', array('id' => $result->getId()));
        return str_replace('index/index', 'customer/edit/id/' . $result->getId(), $baseUrl);
    } elseif ($type == 'order') {
	    return Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/view', array('order_id' => $result->getId()));
        return str_replace('index/index', 'sales_order/view/order_id/' . $result->getId(), $baseUrl);
    } elseif ($type == 'product') {
	    return Mage::helper('adminhtml')->getUrl('adminhtml/catalog_product/edit', array('id' => $result->getId()));
        return str_replace('index/index', 'catalog_product/edit/id/' . $result->getId(), $baseUrl);
    } elseif ($type == 'config') {
	    return Mage::helper('adminhtml')->getUrl('adminhtml/system_config/edit', array('section' => $data['section_code']));
        return str_replace('index/index', 'system_config/edit/section/' . $data['section_code'], $baseUrl);
    }

    throw new Exception("Can't determine the type of this result");
}

/**
 * @param $result \Elastica\Result
 */
function getName($result)
{
    $type = $result->getType();
    $data = $result->getData();

    if (in_array($type, array('order', 'customer'))) {
        return $data['fullname'];
    } elseif ($type == 'product') {
        return $data['name'];
    } elseif ($type == 'config') {
        return $data['field'];
    }

    throw new Exception("Can't determine the type of this result");
}

/**
 * @param $result \Elastica\Result
 */
function getDescription($result)
{
    $data = $result->getData();

    if ($result->getType() == 'customer') {
        return $data['email'];
    } elseif ($result->getType() == 'order') {
        return $data['increment_id'] . ' - ' . $data['sku_list'];
    } elseif ($result->getType() == 'product') {
        return '';
        return $data['description'];
    } elseif ($result->getType() == 'config') {
        return $data['section'] . ' > ' . $data['group'];
    }
}

function getId($result)
{
    $data = $result->getData();

    return $result->getType() . '/1/' . $result->getId();
}

function compareResults($a, $b)
{
    if ($a->getType() == $b->getType()){
        return 0;
    } elseif ($a->getType() == 'customer'){
        return -1;
    } elseif ($b->getType() == 'customer'){
        return 1;
    } elseif($a->getType() == 'order') {
        return -1;
    } elseif($b->getType() == 'order') {
        return 1;
    }
    return 1;
}

checkAccess();

$elasticaIndex = getElasticaIndex();

$query = isset($_REQUEST['query']) ? $_REQUEST['query'] : null;
if (!$query) {
    die("Missing ?query");
}

$elasticaQueryString  = new \Elastica\Query\MultiMatch();
$elasticaQueryString->setFields(array(
    'firstname', 'lastname', 'fullname',
    'email', 'increment_id',
    'field', 'section', 'group',
    'name',
    'telephone',
    'fax',
    'code','code_normalized','short_description','product_id'
));
$elasticaQueryString->setQuery($query);
//$elasticaQueryString->setParam('type', 'phrase_prefix');
$elasticaQueryString->setParam('type', 'best_fields');
$elasticaQueryString->setTieBreaker(0.3);


$elasticaQuery = new \Elastica\Query();
$elasticaQuery->setQuery($elasticaQueryString);
$elasticaQuery->setSize(20);

$elasticaResultSet = $elasticaIndex->search($elasticaQuery);
$elasticaResults = $elasticaResultSet->getResults();
$totalResults = $elasticaResultSet->getTotalHits();
usort($elasticaResults, 'compareResults');

?>

<ul>
    <?php if (! $totalResults): ?>
        <li id="error" url="#">
            <div style="float:right; color:red; font-weight:bold;">[Error]</div>
            <strong>No results</strong><br/>
            <span class="informal">No results found for <em><?php echo $query; ?></em></span>
        </li>
    <?php endif; ?>
    <?php foreach ($elasticaResults as $elasticaResult): $data = $elasticaResult->getData(); ?>
        <li id="<?php echo getId($elasticaResult); ?>" url="<?php echo getResultUrl($elasticaResult); ?>/">
            <div style="float:right; color:red; font-weight:bold;">[
                <?php echo $elasticaResult->getType(); ?>
            ]</div>
            <strong><?php echo getName($elasticaResult); ?></strong><br/>
            <span class="informal">
                <?php echo getDescription($elasticaResult); ?>
            </span>
        </li>
    <?php endforeach; ?>
</ul>
