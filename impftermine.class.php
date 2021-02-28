<?php
    /**
     * class Impftermine
     * Please use this PHP class at your own RESPONSIBILITY and RISK. 
     * 
     * @author      Sebastian Fuhrmann <sebastian.fuhrmann@rz-fuhrmann.de>
     * @copyright   (C) 2020-2021 Rechenzentrum Fuhrmann Inh. Sebastian Fuhrmann
     * 
     * Roadmap:
     * - add/improve caching
     * - clean-up code
     * - add examples and helper functions for looping through vaccination centers and vaccines
     * - add logging
     */
    class Impftermine {
        private $cachingDir = __DIR__.'/cache/';

        public function __construct(){
            
        }

        public function setCachingDir ($path){
            if (file_exists($path)){
                $this->cachingDir = $path.'/'; 
            }
        }

        private function isCachingEnabled(){
            return !!file_exists($this->cachingDir); 
        }

        private function doRequest($url, $cfg = array("cachingTime" => 1)){
            $res = null; 

            // write cache file, but don't re-use it long
            if (!isset($cfg["cachingTime"])) $cfg["cachingTime"] = 1; 

            $cache_fn = $this->cachingDir.md5($url).".json";
            if ($this->isCachingEnabled() && file_exists($cache_fn) && time() < filemtime($cache_fn)+$cfg["cachingTime"]){
                // @todo: Error handling
                $res = json_decode(file_get_contents($cache_fn), true); 
            } else {
                $ch = curl_init(); 
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                $raw = curl_exec($ch); 
                $info = curl_getinfo($ch);
                curl_close($ch); 

                if ($info["http_code"] == 200 && ($json = json_decode($raw, true))){
                    $res = $json; 
                    if ($this->isCachingEnabled()){
                        file_put_contents($cache_fn, json_encode($json, JSON_PRETTY_PRINT));
                    }
                }
            }
            
            return $res; 
        }

        /**
         * getVaccines()
         * returns a list of available vaccines
         * Endpoint: https://www.impfterminservice.de/assets/static/its/vaccination-list.json
         */
        public function getVaccines (){
            $list = $this->doRequest(
                        'https://www.impfterminservice.de/assets/static/its/vaccination-list.json', 
                        array(
                            "cachingTime" => 60*60*24     // will not change that often ;)
                        )
                    );
            return $list;
        }

        /**
         * getVaccinationCenters()
         * returns a list of available and active vaccination centers
         * Endpoint: https://www.impfterminservice.de/assets/static/impfzentren.json
         */
        public function getVaccinationCenters(){
            $list = $this->doRequest(
                        'https://www.impfterminservice.de/assets/static/impfzentren.json',
                        array(
                            "cachingTime" => 60*60*24     // will not change that often ;)
                        )
                    );
            return $list; 
        }
    }
    
?>