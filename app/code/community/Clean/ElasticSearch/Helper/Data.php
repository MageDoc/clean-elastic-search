<?php

class Clean_ElasticSearch_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var array
     */
    protected $_config = array();

    /**
     * @var array
     */
    protected $_includedAttributes = array('visibility', 'image', 'tax_class_id');

    /**
     * Allowed languages
     * Example: array('en_US' => 'en', 'fr_FR' => 'fr')
     *
     * @var array
     */
    protected $_languageCodes = array();

    /**
     * @var array Stop languages for token filter
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-stop-tokenfilter.html
     */
    protected $_stopLanguages = array(
        'arabic', 'armenian', 'basque', 'brazilian', 'bulgarian', 'catalan', 'czech', 'danish', 'dutch', 'english',
        'finnish', 'french', 'galician', 'german', 'greek', 'hindi', 'hungarian', 'indonesian', 'irish', 'italian',
        'latvian', 'norwegian', 'persian', 'portuguese', 'romanian', 'russian', 'sorani', 'spanish', 'swedish',
        'thai', 'turkish',
    );

    /**
     * @var array Snowball languages
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-snowball-tokenfilter.html
     */
    protected $_snowballLanguages = array(
        'Armenian', 'Basque', 'Catalan', 'Danish', 'Dutch', 'English', 'Finnish', 'French',
        'German', 'Hungarian', 'Italian', 'Kp', 'Lovins', 'Norwegian', 'Porter', 'Portuguese',
        'Romanian', 'Russian', 'Spanish', 'Swedish', 'Turkish',
    );

    /**
     * Returns Elasticsarch client with given store configuration
     *
     * @param mixed $store
     * @return \Elastica\Client
     */
    public function getClient($store = null)
    {
        return Mage::getSingleton('cleanelastic/index')->getClient();
    }

    /**
     * @param mixed $store
     * @return array
     */
    public function getEngineConfigData($store = null)
    {
        $store = Mage::app()->getStore($store);

        if (!isset($this->_config[$store->getId()])) {
            $config = Mage::getStoreConfig('catalog/search', $store);
            $data = array();
            foreach ($config as $key => $value) {
                $matches = array();
                if (preg_match("#^elasticsearch_(.*)#", $key, $matches)) {
                    $data[$matches[1]] = $value;
                }
            }
            $servers = array();
            foreach (explode(',', $data['servers']) as $server) {
                $server = trim($server);
                if (substr($server, 0, 4) !== 'http') {
                    $server = 'http://' . $server;
                }
                $info = parse_url($server);
                $host = $info['host'];
                $path = '/';
                if (isset($info['path'])) {
                    $path = trim($info['path'], '/') . '/';
                }
                if (isset($info['user']) && isset($info['pass'])) {
                    $host = $info['user'] . ':' . $info['pass'] . '@' . $host;
                }
                if (isset($info['port'])) {
                    $port = $info['port'];
                } else {
                    $port = ($info['scheme'] == 'https') ? 443 : 80;
                }
                $connection = array(
                    'transport' => ucfirst($info['scheme']),
                    'host'      => $host,
                    'port'      => $port,
                    'path'      => $path,
                    'timeout'   => (int) $data['timeout'],
                );
                if ($info['scheme'] == 'https' && !$data['verify_host']) {
                    $connection['curl'] = array(
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    );
                }
                $servers[] = $connection;
            }
            $data['servers'] = $servers;
            $this->_config[$store->getId()] = $data;
        }

        return $this->_config[$store->getId()];
    }

    /**
     * Returns configured analyzers of given store
     *
     * @param mixed $store
     * @return array
     */
    public function getStoreAnalyzers($store = null)
    {
        $indexSettings = $this->getStoreIndexSettings($store);
        $analyzers = array_keys($indexSettings['analysis']['analyzer']);

        return $analyzers;
    }

    /**
     * Returns indexation analyzers and filters configuration
     *
     * @param mixed $store
     * @return array
     */
    public function getStoreIndexSettings($store = null)
    {
        $store = Mage::app()->getStore($store);
        $cacheId = 'elasticsearch_index_settings_' . $store->getId();
        if (Mage::app()->useCache('config')) {
            $indexSettings = Mage::app()->loadCache($cacheId);
            if ($indexSettings) {
                return unserialize($indexSettings);
            }
        }

        $config = $this->getEngineConfigData($store);
        $indexSettings = array();
        $indexSettings['number_of_replicas'] = (int) $config['number_of_replicas'];
        $indexSettings['number_of_shards'] = (int) $config['number_of_shards'];
        $indexSettings['analysis']['char_filter'] = array(
            "normalizeCodeFilter" => array(
                "pattern"   => "[^A-Za-z0-9а-яА-Я]",
                "type"      =>"pattern_replace",
                "replacement" => ""
            )
        );
        $indexSettings['analysis']['analyzer'] = array(
            'std' => array( // Will allow query 'shoes' to match better than 'shoe' which the stemmed version
                'tokenizer' => 'standard',
                'char_filter' => 'html_strip', // strip html tags
                'filter' => array('standard', 'elision', 'asciifolding', 'lowercase', 'stop', 'length'),
            ),
            'brand' => array(
                'tokenizer' => 'standard',
                'char_filter' => 'html_strip', // strip html tags
                'filter' => array('standard', 'asciifolding', 'lowercase', 'stop', 'length', 'latinTransform'),
            ),
            'keyword' => array(
                'tokenizer' => 'keyword',
                'char_filter' => 'normalizeCodeFilter',
                'filter' => array('asciifolding', 'lowercase'),
            ),
            'keyword_prefix' => array(
                'tokenizer' => 'keyword',
                'char_filter' => 'normalizeCodeFilter',
                'filter' => array('asciifolding', 'lowercase', 'edge_ngram_front'),
            ),
            'keyword_suffix' => array(
                'tokenizer' => 'keyword',
                'char_filter' => 'normalizeCodeFilter',
                'filter' => array('asciifolding', 'lowercase', 'edge_ngram_back'),
            ),
            'text_prefix' => array(
                'tokenizer' => 'standard',
                'char_filter' => 'html_strip', // strip html tags
                'filter' => array('standard', 'elision', 'asciifolding', 'lowercase', 'stop', 'edge_ngram_front'),
            ),
            'text_suffix' => array(
                'tokenizer' => 'standard',
                'char_filter' => 'html_strip', // strip html tags
                'filter' => array('standard', 'elision', 'asciifolding', 'lowercase', 'stop', 'edge_ngram_back'),
            ),
        );
        $indexSettings['analysis']['filter'] = array(
            'edge_ngram_front' => array(
                'type' => 'edgeNGram',
                'min_gram' => 2,
                'max_gram' => 10,
                'side' => 'front',
            ),
            'edge_ngram_back' => array(
                'type' => 'edgeNGram',
                'min_gram' => 2,
                'max_gram' => 10,
                'side' => 'back',
            ),
            'strip_spaces' => array(
                'type' => 'pattern_replace',
                'pattern' => '\s',
                'replacement' => '',
            ),
            'strip_dots' => array(
                'type' => 'pattern_replace',
                'pattern' => '\.',
                'replacement' => '',
            ),
            'strip_hyphens' => array(
                'type' => 'pattern_replace',
                'pattern' => '-',
                'replacement' => '',
            ),
            'stop' => array(
                'type' => 'stop',
                'stopwords' => '_none_',
            ),
            'length' => array(
                'type' => 'length',
                'min' => 2,
            ),
            "latinTransform" => array(
                "type" => "icu_transform",
                "id"   => "Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC"
            ),
            "yo_filter" => array(
                "type" => "mapping",
                "mappings" => array(
                    "ё => е",
                    "Ё => Е"
                )
            )
        );
        /** @var $store Mage_Core_Model_Store */
        $languageCode = $this->getLanguageCodeByStore($store);
        $language = Zend_Locale_Data::getContent('en_GB', 'language', $languageCode);
        $languageExists = true;
        if (!in_array($language, $this->_snowballLanguages)) {
            $parts = explode(' ', $language); // try with potential first string
            $language = $parts[0];
            if (!in_array($language, $this->_snowballLanguages)) {
                $languageExists = false; // language not present by default in elasticsearch
            }
        }
        if ($languageExists) {
            $languageFilters = array(
                'standard', 'elision', 'asciifolding', 'lowercase', 'stop'
            );

            if ($language == 'Russian'
                && $this->hasPlugin('elasticsearch-analysis-morphology')) {
                // Define russian_morphology filter according to current language
                $filter = 'my_stopwords';
                $indexSettings['analysis']['filter'][$filter] = array(
                    'type' => 'stop',
                    'stopwords' => 'а,без,более,бы,был,была,были,было,быть,в,вам,вас,весь,во,вот,все,всего,всех,вы,где,да,даже,для,до,его,ее,если,есть,еще,же,за,здесь,и,из,или,им,их,к,как,ко,когда,кто,ли,либо,мне,может,мы,на,надо,наш,не,него,нее,нет,ни,них,но,ну,о,об,однако,он,она,они,оно,от,очень,по,под,при,с,со,так,также,такой,там,те,тем,то,того,тоже,той,только,том,ты,у,уже,хотя,чего,чей,чем,что,чтобы,чье,чья,эта,эти,это,я,a,an,and,are,as,at,be,but,by,for,if,in,into,is,it,no,not,of,on,or,such,that,the,their,then,there,these,they,this,to,was,will,with',
                );
                $stemmer = array('russian_morphology', 'english_morphology', 'my_stopwords');
            } elseif ($language == 'English') {
                $stemmer = 'kstem'; // less agressive than snowball
            } else {
                // Define snowball filter according to current language
                $stemmer = 'snowball';
                $indexSettings['analysis']['filter'][$stemmer] = array(
                    'type' => 'snowball',
                    'language' => $language,
                );
            }

            if (is_array($stemmer)){
                $languageFilters = array_merge($languageFilters, $stemmer);
            } else {
                $languageFilters []= $stemmer;
            }
            $languageFilters []= 'length';
            // Define a custom analyzer adapted to the store language
            $indexSettings['analysis']['analyzer']['language'] = array(
                'type' => 'custom',
                'tokenizer' => 'standard',
                'char_filter' => 'html_strip', // strip html tags
                'filter' => $languageFilters,
            );

            // Define stop words filter according to current language if possible
            $stopwords = strtolower($language);
            if (in_array($stopwords, $this->_stopLanguages)) {
                $indexSettings['analysis']['filter']['stop']['stopwords'] = '_' . $stopwords . '_';
            }
        }

//        foreach ($indexSettings['analysis']['analyzer'] as &$analyzer) {
//            array_unshift($analyzer['filter'], 'icu_folding');
//        }
//        unset($analyzer);

        $indexSettings = new Varien_Object($indexSettings);

        Mage::dispatchEvent('clean_elasticsearch_index_settings', array(
            'client' => $this,
            'store' => $store,
            'settings' => $indexSettings,
        ));

        $indexSettings = $indexSettings->getData();

        if (Mage::app()->useCache('config')) {
            $lifetime = $this->getCacheLifetime();
            Mage::app()->saveCache(serialize($indexSettings), $cacheId, array('config'), $lifetime);
        }

        return $indexSettings;
    }

    /**
     * @return int
     */
    public function getCacheLifetime()
    {
        return Mage::getStoreConfig('core/cache/lifetime');
    }

    /**
     * Returns language code of specified locale code
     *
     * @param string $localeCode
     * @return bool
     */
    public function getLanguageCodeByLocaleCode($localeCode)
    {
        $localeCode = (string) $localeCode;
        if (!$localeCode) {
            return false;
        }

        if (!isset($this->_languageCodes[$localeCode])) {
            $languages = $this->getSupportedLanguages();
            $this->_languageCodes[$localeCode] = false;
            foreach ($languages as $code => $locales) {
                if (is_array($locales)) {
                    if (in_array($localeCode, $locales)) {
                        $this->_languageCodes[$localeCode] = $code;
                    }
                } elseif ($localeCode == $locales) {
                    $this->_languageCodes[$localeCode] = $code;
                }
            }
        }

        return $this->_languageCodes[$localeCode];
    }

    /**
     * Returns store language code
     *
     * @param mixed $store
     * @return bool
     */
    public function getLanguageCodeByStore($store = null)
    {
        return $this->getLanguageCodeByLocaleCode($this->getLocaleCode($store));
    }

    /**
     * Returns store locale code
     *
     * @param mixed $store
     * @return string
     */
    public function getLocaleCode($store = null)
    {
        return Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $store);
    }

    /**
     * Retrieve searchable types
     *
     * @param mixed $store
     * @return array
     */
    public function getStoreTypes($store = null)
    {
        $types = array('product', 'config', 'customer', 'order');

        return $types;
    }

    /**
     * Defines supported languages for snowball filter
     *
     * @return array
     */
    public function getSupportedLanguages()
    {
        $languages = array(
            /**
             * SnowBall filter based
             */
            // Danish
            'da' => 'da_DK',
            // Dutch
            'nl' => 'nl_NL',
            // English
            'en' => array('en_AU', 'en_CA', 'en_NZ', 'en_GB', 'en_US'),
            // Finnish
            'fi' => 'fi_FI',
            // French
            'fr' => array('fr_CA', 'fr_FR'),
            // German
            'de' => array('de_DE','de_CH','de_AT'),
            // Hungarian
            'hu' => 'hu_HU',
            // Italian
            'it' => array('it_IT','it_CH'),
            // Norwegian
            'nb' => array('nb_NO', 'nn_NO'),
            // Portuguese
            'pt' => array('pt_BR', 'pt_PT'),
            // Romanian
            'ro' => 'ro_RO',
            // Russian
            'ru' => 'ru_RU',
            // Spanish
            'es' => array('es_AR', 'es_CL', 'es_CO', 'es_CR', 'es_ES', 'es_MX', 'es_PA', 'es_PE', 'es_VE'),
            // Swedish
            'sv' => 'sv_SE',
            // Turkish
            'tr' => 'tr_TR',

            /**
             * Lucene class based
             */
            // Czech
            'cs' => 'cs_CZ',
            // Greek
            'el' => 'el_GR',
            // Thai
            'th' => 'th_TH',
            // Chinese
            'zh' => array('zh_CN', 'zh_HK', 'zh_TW'),
            // Japanese
            'ja' => 'ja_JP',
            // Korean
            'ko' => 'ko_KR'
        );

        $languages = new Varien_Object($languages);

        Mage::dispatchEvent('clean_elasticsearch_supported_languages', array(
            'languages' => $languages,
        ));

        return $languages->getData();
    }

    /**
     * @return bool
     */
    public function isDebugEnabled()
    {
        $config = $this->getEngineConfigData();

        return array_key_exists('enable_debug_mode', $config) && $config['enable_debug_mode'];
    }

    /**
     * Checks if search results are enabled for given entity and store
     *
     * @param string $entity
     * @param mixed $store
     * @return bool
     */
    public function isSearchEnabled($entity, $store = null)
    {
        return $this->isIndexationEnabled($entity, $store) &&
            Mage::getStoreConfigFlag('elasticsearch/'. $entity .'/enable_search', $store);
    }

    /**
     * Checks if indexation is enabled/available for given entity and store
     *
     * @param string $entity
     * @param mixed $store
     * @return bool
     */
    public function isIndexationEnabled($entity, $store = null)
    {
        return Mage::getStoreConfigFlag('elasticsearch/'. $entity .'/enable', $store);
    }

    /**
     * @return Varien_Db_Adapter_Pdo_Mysql
     */
    protected function _getAdapter()
    {
        return $this->_getResource()->getConnection('read');
    }

    /**
     * @return Mage_Core_Model_Resource
     */
    protected function _getResource()
    {
        return Mage::getSingleton('core/resource');
    }

    /**
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @param string $value
     * @param mixed $store
     * @return mixed
     */
    protected function _formatValue($attribute, $value, $store = null)
    {
        if ($attribute->getBackendType() == 'decimal' || $attribute->getFrontendClass() == 'validate-number') {
            if (strpos($value, ',')) {
                $value = array_unique(array_map('floatval', explode(',', $value)));
            } else {
                $value = (float) $value;
            }
        } elseif ($attribute->getSourceModel() == 'eav/entity_attribute_source_boolean' || $attribute->getFrontendInput() == 'boolean') {
            $value = (bool) $value;
        } elseif ($attribute->usesSource() || $attribute->getFrontendClass() == 'validate-digits') {
            if (strpos($value, ',')) {
                $value = array_unique(array_map('intval', explode(',', $value)));
            } else {
                $value = (int) $value;
            }
        } elseif ($attribute->getFrontendInput() == 'media_image') {
            if ($value == 'no_selection') {
                $value = null;
            } elseif (!Mage::getStoreConfigFlag('elasticsearch/product/enable_thumbnails_generation', $store)) {
                $value = Mage::app()->getStore($store)->getBaseUrl('media') . 'catalog/product' . $value;
            } else {
                try {
                    $imgSize = Mage::getStoreConfig('elasticsearch/product/image_size', $store);
                    $model = Mage::getSingleton('catalog/product_image')
                        ->setDestinationSubdir('image')
                        ->setBaseFile($value);

                    if (!$model->isCached()) {
                        $processor = new Varien_Image($model->getBaseFile());

                        $model->setImageProcessor($processor)
                            ->setWidth($imgSize)
                            ->setHeight($imgSize)
                            ->resize();

                        $model->getImageProcessor()->save($model->getNewFile());
                    }

                    $baseDir = Mage::getBaseDir('media');
                    $path = str_replace($baseDir . DS, '', $model->getNewFile());
                    $value = Mage::app()->getStore($store)->getBaseUrl('media') . str_replace(DS, '/', $path);
                } catch (Exception $e) {
                    $value = Mage::getDesign()->setStore($store)->getSkinUrl(
                        'images/catalog/product/placeholder/image.jpg',
                        array('_area' => 'frontend')
                    );
                }
            }
        }

        return $value;
    }

    /**
     * @return bool
     */
    public function getShowCategoryPath()
    {
        return Mage::getStoreConfigFlag('elasticsearch/category/show_path');
    }

    /**
     * Adds Elasticsearch header in response for easy debugging
     */
    public function addResponseHeader()
    {
        if (Mage::getStoreConfigFlag('elasticsearch/general/enable_response_header')) {
            preg_match('#@version\s+(\d+\.\d+\.\d+)#', file_get_contents(__FILE__), $matches);
            Mage::app()->getResponse()->setHeader('Wyomind-Elasticsearch', $matches[1], true);
        }
    }

    public function getIndexName()
    {
        $indexName = (string)Mage::getConfig()->getNode('global/elasticsearch/index');
        if (!$indexName) {
            throw new Exception("No index name is defined");
        }

        return $indexName;
    }

    public function getHost()
    {
        return (string)Mage::getConfig()->getNode('global/elasticsearch/host');
    }

    public function getPort()
    {
        return (string)Mage::getConfig()->getNode('global/elasticsearch/port');
    }

    public function getSecret()
    {
        return (string)Mage::getConfig()->getNode('global/elasticsearch/secret');
    }

    public function hasPlugin($name)
    {
        $nodes = $this->getClient()->getCluster()->getNodes();
        $node = reset($nodes);
        return $node && $node->getInfo()->hasPlugin($name);
    }
}