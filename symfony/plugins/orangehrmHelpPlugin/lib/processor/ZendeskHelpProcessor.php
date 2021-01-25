<?php
/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software; you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program;
 * if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor,
 * Boston, MA  02110-1301, USA
 */

class ZendeskHelpProcessor implements HelpProcessor {

    const DEFAULT_CONTENT_TYPE = "application/json";

    protected $helpConfigService;

    /**
     * @return mixed
     */
    public function getHelpConfigService() {
        if (!$this->helpConfigService instanceof HelpConfigService) {
            $this->helpConfigService = new HelpConfigService();
        }
        return $this->helpConfigService;
    }

    /**
     * @param mixed $helpConfigService
     */
    public function setHelpConfigService($helpConfigService) {
        $this->helpConfigService = $helpConfigService;
    }

    public function getBaseUrl() {
        return $this->getHelpConfigService()->getBaseHelpUrl();
    }

    public function getSearchUrlFromQuery($query=null,$labels=[],$categorieIds=[]) {
        $mainUrl=$this->getBaseUrl().'/api/v2/help_center/articles/search.json?';
        if($query!=null){
            $mainUrl.='query='.$query;
        }
        if(count($labels)>0){
            if(substr($mainUrl, -1)!='?'){
                $mainUrl.='&';
            }
            $mainUrl.='label_names=';
            foreach($labels as $label){
                $mainUrl.=$label.',';
            }
            $mainUrl= substr($mainUrl,0,-1);
        }
        if(count($categorieIds)>0){
            if(substr($mainUrl, -1)!='?'){
                $mainUrl.='&';
            }
            $mainUrl.='category=';
            foreach($categorieIds as $categoryId){
                $mainUrl.=$categoryId.',';
            }
            $mainUrl= substr($mainUrl,0,-1);
        }
        return $mainUrl;
    }

    public function getSearchUrl($label) {
        return $this->getBaseUrl().'/api/v2/help_center/articles/search.json?label_names='.$label;
    }

    public function getRedirectUrl($label) {

        $searchUrl = $this->getSearchUrl($label);

        $results = $this->sendQuery($searchUrl);
        if ($results['response']) {
            $response = json_decode($results['response'], true);
        }
        $count = $response['count'];
        if (($count >= 1) && ($results['responseCode'] == 200)) {
            $redirectUrl = $response['results'][0]['html_url'];
        } else {
            $redirectUrl = $this->getDefaultRedirectUrl();
        }
        return $redirectUrl;
    }
    protected function sendQuery($url, $contentType = self::DEFAULT_CONTENT_TYPE) {
        $headerOptions = array();

        $headerOptions[GuzzleHttp\RequestOptions::ALLOW_REDIRECTS]=true;
        $headerOptions[GuzzleHttp\RequestOptions::TIMEOUT]=30;
        $headerOptions[GuzzleHttp\RequestOptions::VERSION]='1.1';
        $headerOptions[GuzzleHttp\RequestOptions::HEADERS]=[
            'Content-Type' => $contentType
        ];
        $client = new GuzzleHttp\Client();
        try {
            $response = $client->get($url, $headerOptions);
        } catch (Exception $e){
            return null;
        }
        $body = $response->getBody();
        $responseCode = $response->getStatusCode();
        return array(
            'responseCode' => $responseCode,
            'response' => $body,
        );
    }

    public function getDefaultRedirectUrl() {
        return $this->getBaseUrl().'/hc/en-us';
    }

    public function getRedirectUrlList($query=null,$labels=[],$categorieIds=[]) {
        if($query==null && $labels==[] && $categorieIds==[]){
            return [];
        }
        $searchUrl = $this->getSearchUrlFromQuery($query,$labels,$categorieIds);
        $results = $this->sendQuery($searchUrl);
        if ($results['response']) {
            $response = json_decode($results['response'], true);
        }
        $redirectUrls=array();
        $count = $response['count'];
        if (($count >= 1) && ($results['responseCode'] == 200)) {
            foreach ($response['results'] as $result){
                $redirectUrl = $result['html_url'];
                $name=$result['name'];
                array_push($redirectUrls,array('name'=>$name,'url'=>$redirectUrl));
            }
            return $redirectUrls;
        } else {
            return [];
        }
    }

    public function getCategoryRedirectUrl($category){
        $url = $this->getBaseUrl().'/api/v2/help_center/categories/'.$category;
        $results = $this->sendQuery($url);
        if ($results['response']) {
            $response = json_decode($results['response'], true);
        }
        if (($results['responseCode'] == 200)) {
            $redirectUrl = $response['category']['html_url'];
        } else {
            $redirectUrl = $this->getDefaultRedirectUrl();
        }
        return $redirectUrl;
    }

    public function getCategoriesFromSearchQuery($query=null){
        $url = $this->getBaseUrl().'/api/v2/help_center/categories';
        $results = $this->sendQuery($url);
        if ($results['response']) {
            $response = json_decode($results['response'], true);
        }
        $final = array();
        if (($results['responseCode'] == 200)) {
            foreach ($response['categories'] as $category){
                $redirectUrl = $category['html_url'];
                $name =$category['name'];
                if($query!=null) {
                    if (strpos($name, $query)!==false) {
                        array_push($final, array('name' => $name, 'url' => $redirectUrl));
                    }
                } else {
                    array_push($final, array('name' => $name, 'url' => $redirectUrl));
                }
            }
        }
        return $final;
    }
}
