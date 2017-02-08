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

    public function main()
    {
        $this->_jar = new CookieJar();
        $this->_client = new Client([
            'cookies' => $this->_jar
        ]);

        $this->_table = $this->helper('Table');

        $this->_setKeyAndSecret();
        $this->_getMarkets();
    }

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

    private function _setApiSignature()
    {
        if (($this->_apiKey != $this->_apiSecret) != false) {
            $this->_url = "{$this->_url}?apikey={$this->_apiKey}&nonce=" . time();

            return hash_hmac(
                'sha512',
                $this->_url,
                $this->_apiSecret
            );
        }

        return false;
    }

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

    private function _parseMarkets($data = [])
    {

        $this->_headers[] = array_values(array_keys($data['result'][0]));

        foreach ($data['result'] as $i) {
            $this->_headers[] =
                array_values($i)
            ;
        }

        $this->_table->output($this->_headers);
    }
}
