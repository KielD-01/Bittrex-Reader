<?php
namespace App\Shell;

use Cake\Cache\Cache;
use Cake\Console\Shell;
use Cake\Log\Log;
use Cake\Shell\Helper\TableHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;


class ReaderShell extends Shell
{

    private $_apiKey = false;
    private $_apiSecret = false;
    private $_apiSignature = false;
    private $_url = 'https://bittrex.com/api/v1.1/public/getmarkets';

    private $_headers = [];

    /**
     * @var TableHelper
     */
    private $_table;

    /**
     * @var Client
     */
    private $_client;

    /**
     * @var CookieJar
     */
    private $_jar;

    private $_ignorableFields = [];

    /**
     * Init method
     */
    public function main()
    {
        $this->_jar = new CookieJar();
        $this->_client = new Client([
            'cookies' => $this->_jar
        ]);

        $this->_table = $this->helper('Table');

        $this->_setKeyAndSecret();

        $this->_addIgnoreField('LogoUrl')
            ->_addIgnoreField('IsSponsored');

        $this->_getMarkets();
    }


    /**
     * Fluent setter to write ignorable fields
     *
     * @param bool $field
     * @return $this
     */
    private function _addIgnoreField($field = false)
    {
        $this->_ignorableFields[] = $field;

        return $this;
    }

    /**
     * Setting up keys
     */
    private function _setKeyAndSecret()
    {
        if (($this->_apiKey = Cache::read('api_key')) === false) {
            $this->_apiKey = $this->in('Введите API Key : ');

            if ($this->_apiKey != false) {
                Cache::write('api_key', $this->_apiKey);
            }
        }

        if (($this->_apiSecret = Cache::read('api_secret')) === false) {
            $this->_apiSecret = $this->in('Введите API Secret : ');

            if ($this->_apiSecret != false) {
                Cache::write('api_secret', $this->_apiSecret);
            }
        }
    }

    /**
     * Setting up signature
     *
     * @return bool|string
     */
    private function _setApiSignature()
    {
        if (($this->_apiKey != $this->_apiSecret) != false) {
            $url = "{$this->_url}?apikey={$this->_apiKey}&nonce=" . time();

            return hash_hmac(
                'sha512',
                $url,
                $this->_apiSecret
            );
        }

        return false;
    }

    /**
     * Sending request to parse markets
     */
    private function _getMarkets()
    {
        $options = [];

        if (($this->_apiKey != $this->_apiSecret) != false) {
            $options['headers'] = [
                'apisign' => $this->_setApiSignature()
            ];
        }

        $this->_parseMarkets(
            json_decode(
                $this->_client->get($this->_url, $options)
                    ->getBody()
                    ->getContents(), 1
            )
        );
    }

    /**
     * Parsing markets data
     *
     * @param array $data
     */
    private function _parseMarkets($data = [])
    {
        foreach ($data['result'] as $i) {

            $this->_removeFields($i);

            if ($this->_headers == []) {
                $this->_headers[] = array_values(array_keys($i));
            }

            $this->_headers[] =
                array_values($i);
        }

        $this->_table->output($this->_headers);
    }

    /**
     * Removing fields You don't want to use
     *
     * @param $market
     */
    private function _removeFields(&$market)
    {
        foreach ($this->_ignorableFields as $ignorableField) {
            unset($market[$ignorableField]);
        }
    }
}
