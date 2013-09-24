<?php

/* Copyright (c) 2013, HotDocs Limited
   Use, modification and redistribution of this source is subject
   to the New BSD License as set out in LICENSE.TXT. */

/**
 * An abstract class that forms the basis for all request types.
 */
abstract class Request {
  protected $billingRef;
  protected $packageId;
  protected $packageFilePath;
  protected $content;
  
  public function getBillingRef() {
    return $this->billingRef;
  }
  
  public function getPackageId() {
    return $this->packageId;
  }
  
  public function getPackageFilePath() {
    return $this->packageFilePath;
  }
  
  public function getContent() {
    return $this->content;
  }
  
  abstract public function getPathPrefix();
  abstract public function getQuery();
  abstract public function getMethod();
  abstract public function getHmacParams();
}

/**
 * A request for creating an embedded session.
 */
class CreateSessionRequest extends Request {

  private $interviewFormat;
  private $outputFormat;
  private $theme;
  private $showDownloadLinks;
  private $settings = array();
  
  public function __construct(
      $packageId,
      $packageFilePath,
      $billingRef = NULL,
      $answers = NULL,
      $interviewFormat = 'JavaScript',
      $outputFormat = 'Native',
      $theme = NULL,
      $showDownloadLinks = TRUE) {
    $this->packageId = $packageId;
    $this->packageFilePath = $packageFilePath;
    $this->billingRef = $billingRef;
    $this->content = $answers;
    $this->interviewFormat = $interviewFormat;
    $this->outputFormat = $outputFormat;
    $this->theme = $theme;
    $this->showDownloadLinks = $showDownloadLinks;
  }
  
  public function setSetting($name, $value) {
    $this->settings[$name] = $value;
  }
  
  public function getContent() {
    return $this->content;
  }
  
  public function getPathPrefix() {
    return '/embed/newsession';
  }
  
  public function getQuery() {
    $query = 'interviewformat=' . rawurlencode($this->interviewFormat)
        . '&outputformat=' . rawurlencode($this->outputFormat)
        . '&showdownloadlinks=' . rawurlencode($this->showDownloadLinks);
        
    if ($this->billingRef != NULL) {
       $query .= '&billingref=' . rawurlencode($this->billingRef);
    }
    
    if ($this->theme != NULL) {
      $query .= '&theme=' . rawurlencode($this->theme);
    }
    
    foreach($this->settings as $name=>$value) {
      $query .= '&$this->name=' . rawurlencode($value);
    }
    return $query;
  }
  
  public function getMethod() {
    return 'POST';
  }
  
  public function getHmacParams() {
    return array($this->packageId, $this->billingRef,
    $this->interviewFormat, $this->outputFormat, $this->settings);
  }
}

/**
 * A request for resuming a previously saved embedded session.
 */
class ResumeSessionRequest extends Request {

  /**
   * @param string $snapshot
   */
  public function __construct($snapshot) {
    $this->content = $snapshot;
  }
 
  public function getPathPrefix() {
    return '/embed/resumesession';
  }

  public function getQuery() {
    return NULL;
  }

  public function getMethod() {
    return 'POST';
  }

  public function getHmacParams() {
    return array($this->content);
  }
}

/**
 * A request for uploading a package to Cloud Services.
 */
class UploadPackageRequest extends Request {
  
  public function __construct($packageId, $packageFilePath) {
    $this->packageId = $packageId;
    $this->content = file_get_contents($packageFilePath);
  }
    
  public function getPathPrefix() {
    return '/hdcs';
  }

  public function getQuery() {
    return NULL;
  }

  public function getMethod() {
    return 'PUT';
  }

  public function getHmacParams() {
    return array($this->packageId, NULL, TRUE, $this->billingRef);
  }
}

/**
 * A class for sending requests to HotDocs Cloud Services.
 */
class Client {

  private $subscriberId;
  private $signingKey;
  private $address;
  private $proxy;

/**
 * @param string $subscriberId
 * @param string $signingKey
 * @param string $address
 * @param string $proxy
 */
  public function __construct(
      $subscriberId,
      $signingKey,
      $address = 'https://cloud.hotdocs.ws',
      $proxy = NULL) {
    $this->subscriberId = $subscriberId;
    $this->signingKey = $signingKey;
    $this->address = $address;
    $this->proxy = $proxy;
  }
  
  /**
   * Sends a request to Cloud Services.  If the request refers to a package that
   * is not in the Cloud Services cache, this method will automatically upload
   * the package and retry the request.
   * @param  Request $request
   * @return stringe
   */
  public function sendRequest($request) {
    $ch = $this->getConn($request);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status == 404) {
      $uploadCh = $this->getConn(new UploadPackageRequest($request->getPackageId(),
          $request->getPackageFilePath()));
      curl_exec($uploadCh);
      if (curl_getinfo($uploadCh, CURLINFO_HTTP_CODE) < 300) {
        curl_close($ch);
        curl_close($uploadCh);
        // The upload succeeded, so retry the original request.
        $ch = $this->getConn($request);
        $response = curl_exec($ch);
      } else if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 409) {
        curl_close($ch);
        $ch = $uploadCh;
      } else {
        curl_close($uploadCh);
      }
      
      // If the upload status is 409, then the package was already in the
      // cache, so we'll return the response from the original request.
    }
    
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status >= 300) {
      throw new Exception(curl_error($ch));
    }
    
    return $response;
  }
  
  /**
   * Configures a cURL handle for the request.
   * @param  Request $request
   * @return resource
   */
  private function getConn($request) {
    $url = $this->address . $request->getPathPrefix() . '/' . rawurlencode($this->subscriberId);
    if (strlen($request->getPackageId()) > 0) {
      $url .= '/' . rawurlencode($request->getPackageId());
    }
    if (strlen($request->getQuery()) > 0) {
      $url .= '?' . $request->getQuery();
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->getMethod());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request->getContent());
    if (strlen($request->getContent()) > 0) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/binary')); 
      curl_setopt ($ch, CURLINFO_HEADER_OUT, TRUE);
    }
    if (strlen($this->proxy) > 0) {
      curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
    }
    $this->signAndDate($ch, $request->getHmacParams());
    return $ch;
  }
  
  /**
   * Adds a signature and date to a cURL handle configuration.
   * @param resource $ch
   * @param array    $params
   */
  private function signAndDate($ch, $params) {
    date_default_timezone_set('GMT');
    $timestamp = time();
    array_unshift($params, $this->subscriberId);
    array_unshift($params, date("Y-m-d\TH:i:s\Z", $timestamp)); //yyyy-MM-ddTHH:mm:ssZ
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: ' . $this->getSignature($params),
        'x-hd-date: ' . date("D, d M Y H:i:s T", $timestamp)));
  }
  
  /**
   * Calculates an HMAC given a set of parameters.
   * @param  array $params
   * @return string
   */
  private function getSignature($params) {
    // If there are any bools in $params, convert them to strings.
    // Also convert any arrays to strings of the following format:
    // key0=value0\nkey1=value1\nkey2=value2
    foreach ($params as $name=>&$value) {
      if (is_bool($value)) {
        $value = $value ? "True" : "False";
      }
      elseif (is_array($value)) {
        $strings = array();
        foreach ($value as $n=>$v) {
          array_push($strings, "$n=$v");
        }
        $value = implode("\n", $strings);
      }
    }
    
    $stringToSign = implode("\n", $params);
    $signature = hash_hmac('sha1', $stringToSign, $this->signingKey, TRUE);
    return base64_encode($signature);
  }
}
?>
