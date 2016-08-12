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
class PacklinkSDK extends PacklinkApiCall
{

    protected $dev = true;

    protected $api_key;

    public function __construct($api_key, $module)
    {
        parent::__construct('https://api.packlink.com/', $module);

        $this->api_key = $api_key;
    }

    public function postAnalitics($payload)
    {
        $this->action = 'POST';
        $this->endpoint = 'v1/analytics';
        $this->makeCall($this->getBody($payload));

        return $this->response;
    }


    public function createDraft($payload)
    {
        $this->action = 'POST';
        $this->endpoint = 'v1/shipments';
        $this->makeCall($this->getBody($payload));

        return $this->response;
    }

    protected function getBody(array $fields)
    {
        $return = true;

        // if fields not empty
        if (empty($fields)) {
            $return = false;
        }

        // if not empty
        if ($return) {
            return json_encode($fields);
        }

        return $return;
    }

    /**
    *
    * @return $postal_zones : array(
        array('id' => '76', 'name' => 'France métropolitaine'),
        array('id' => '77', 'name' => 'France Corse')
    */
    public function getPostalZones($params)
    {
        $this->action = 'GET';
        $this->endpoint = 'v1/locations/postalzones/origins?language='.$params['language'].'&platform='.$params['platform'].'&platform_country='.$params['platform_country'].'';

        $this->makeCall();

        return $this->response;
    }



    public function getPostalCodes($params)
    {
        $this->action = 'GET';
        $this->endpoint = 'v1/locations/postalcodes?platform='.$params['platform'].'&platform_country='.$params['platform_country'].'&postalzone='.$params['postalzone'].'&q='.$params['q'];

        $this->makeCall();

        return $this->response;
    }

    public function getCitiesByPostCode($postcode, $iso_code)
    {
        $this->action = 'GET';
        $language = $iso_code.'_'.strtoupper($iso_code);
        $this->endpoint = 'v1/locations/postalcodes/'.$iso_code.'/'.$postcode.'?language='.$language.'&platform=PRO&platform_country='.$iso_code;

        $this->makeCall();

        return $this->response;
    }
}
