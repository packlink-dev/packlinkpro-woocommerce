<?php
/**
* Copyright 2016 OMI Europa S.L (Packlink)

* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at

*  http://www.apache.org/licenses/LICENSE-2.0

* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
*/
abstract class PacklinkApiCall
{
    /**
     *  Host of call
     * @var string
     */
    protected $host;

    /**
     * Endpoint
     * @var string
     */
    protected $endpoint;

    /**
     * Instance of module who use this class
     * @var Module
     */
    protected $module;

    /**
     * Response of call
     * @var string
     */
    protected $response;

    /**
     * Only POST, GET for the moment
     * @var string
     */
    protected $action;

    /**
     * Logs
     * @var array
     */
    protected $logs = array();

    /**
     * If debug mod
     * @var boolean
     */
    protected $debug = false;

    /**
     * 401 User
     * @var string
     */
    protected $user;

    /**
     * 401 Password
     * @var string
     */
    protected $passwd;

    /**
     * Initialization
     */
    public function __construct($host, $module)
    {
        $this->host     = $host;
        $this->module   = $module;


    }

    private function log($message) {
        if ($this->module)
            $this->module->log($message);
    }

    /**
     * Get body
     * @return mixed Create body
     */
    abstract protected function getBody(array $fields);

    /**
     * Make call
     * @param  mixed  $body        body content
     * @param  mixed  $http_header header content
     * @return mixed               Call
     */
    protected function makeCall($body = null, $http_header = null)
    {
        // init uri to call
        $uri_to_call = $this->host.$this->endpoint;

        $this->log('======================================================================================================');
        $this->log('Making new connection to : '.$uri_to_call);

        $stream_context = array();

        if ($body != null) {

            $stream_context = stream_context_create(
                array (
                    'http' => array (
                        'method' => 'POST',
                        'content' => $body
                        )
                )
            );
        } else {
            $stream_context = array();
        }

        $this->response = $this->fileGetContents($uri_to_call, false, $stream_context);
        
        $this->log('Response : '.$this->response);

        if ($this->response != false) {
            $this->response = json_decode($this->response);
        }

    }



    private function fileGetContents($url, $use_include_path = false, $stream_context = null, $curl_timeout = 5)
    {
        if ($stream_context == null && preg_match('/^https?:\/\//', $url)) {
            $stream_context = @stream_context_create(array('http' => array('timeout' => $curl_timeout)));
        }
        if (function_exists('curl_init')) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($curl, CURLOPT_TIMEOUT, $curl_timeout);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            if ($stream_context != null) {
                $opts = stream_context_get_options($stream_context);
               
                if (isset($opts['http']['method']) && strtolower($opts['http']['method']) == 'post') {
                    curl_setopt($curl, CURLOPT_POST, true);
                    if (isset($opts['http']['content'])) {
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $opts['http']['content']);
                    }
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                       "Content-type: application/json",
                        "Content-Length: " . strlen($opts['http']['content']),
                        "Authorization: ".$this->api_key
                    ));
                }
            }
            
            foreach ($opts as $key => $option) {
                foreach ($option as $key => $value) {
                    $this->log('Options of stream context: '.$key.' => '.$value);
                }
            }

            $content = curl_exec($curl);

            $this->log('Curl: '.$curl);

            curl_close($curl);

            return $content;
        } else {
            return false;
        }
    }


}
