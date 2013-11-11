<?php
/**
 * Created by PhpStorm.
 * Author: Forrest Vodden
 * Date: 11/8/13
 * Time: 5:24 PM
 */

namespace loggly;

function nvsprintf($str, array $args) {
  $i = 1;
  foreach ($args as $k => $v) {
    $str = str_replace("%{$k}$", "%{$i}$", $str);
    $i++;
  }
  return vsprintf($str, array_values($args));
}

class Loggly {
  private $token = '';
  private $protocol = 'http';
  private $hostname = 'logs-01.loggly.com';
  private $endpoint = 'inputs';

  private $requestURIMask = '%protocol$s://%host$s/%endpoint$s/%token$s';
  private $requestURI = '';
  private $debug = false;

  private function __construct(){}
  private static function &__getInstance(){
    static $instance = array();
    if(!$instance){
      $inst = new self();
      $instance[0] =& $inst;
    }

    return $instance[0];
  }

  public static function setToken($token){
    $_this =& self::__getInstance();
    $_this->token = $token;

    $_this->buildRequestURI();
  }

  public static function setHostname($hostname){
    $_this =& self::__getInstance();
    $_this->hostname = $hostname;

    $_this->buildRequestURI();
  }

  public static function setEndpoint($endpoint){
    $_this =& self::__getInstance();
    $_this->endpoint = $endpoint;

    $_this->buildRequestURI();
  }

  public static function write($data, $tags = array()){
    $_this =& self::__getInstance();
    $headers = array();

    $msg = '';
    if(isset($data['msg'])){
      $msg = $data['msg'];
      unset($data['msg']);
    }

    if(is_array($data)) $data = $msg . ' ' . json_encode($data);

    if(!empty($tags)){
      array_push($headers, 'X-LOGGLY-TAG: ' . implode(',', $tags));
    }

    if(false !== ($result = $_this->curlRequest('post', $data, $headers))){
      $decoded = json_decode($result, true);
      if(isset($decoded['response']) && $decoded['response'] == 'ok') return true;
    }

    return false;
  }

  private function curlRequest($method = 'post', $data, $headers = array()){
    $_this =& self::__getInstance();

    $method = strtoupper($method);
    $ch = curl_init();

    $headers = array_merge(array(
      'Content-Type: text/plain'
    ), $headers);

    curl_setopt($ch, CURLOPT_URL, $_this->requestURI);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $methods = array(
      'POST' => CURLOPT_POST,
      'PUT' => CURLOPT_PUT
    );

    if(isset($methods[$method])){
      curl_setopt($ch, $methods[$method], 1);
      if($method == 'POST') curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    if(false === ($result = curl_exec($ch))){
      if($this->debug){
        trigger_error(sprintf('Error from cURL: `%s` Error No #%d', curl_error($ch), curl_errno($ch)));
      }

      return false;
    }

    return $result;
  }

  private function buildRequestURI(){
    $_this =& self::__getInstance();
    $_this->requestURI = nvsprintf($_this->requestURIMask, array(
      'protocol' => $this->protocol,
      'host' => $this->hostname,
      'endpoint' => $this->endpoint,
      'token' => $this->token
    ));

    return $this->requestURI;
  }
}