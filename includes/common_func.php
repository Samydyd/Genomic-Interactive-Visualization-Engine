<?php
require_once(realpath(dirname(__FILE__) . "/constants.php"));

ini_set("memory_limit", "2048M");
ini_set('max_execution_time', 300);

// Error codes
// Server-wide issue
define('CONNECTION_FAILED', 1);
define('REF_NOT_READY', 2);

// Reference-wide issue
define('NO_REF_NAMED', 10);
define('TABLE_NOT_READY', 100);
define('LINKED_TABLE_NOT_READY', 101);
define('NO_GENE_SYMBOL_COLUMN', 102);
define('TABLE_FORMAT_ERROR', 103);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

class GIVEException extends Exception {
  protected $userInputRelated;

  public function __construct($message, $code = 0, Exception $previous = null,
    $userInputRelated = false
  ) {
    $this->userInputRelated = $userInputRelated;
    parent::__construct($message, $code, $previous);
  }

  // custom string representation of object
  public function __toString() {
      return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
  }

  public function getJSON() {
    return json_encode(get_object_vars($this));
  }

  public function getSuppressedJSON() {
    $result = $this->userInputRelated ? get_object_vars($this) : [
      'message' => 'Error in GIVE parameters. " .
        "If you believe your parameter is correct, " .
        "please contact the admin of the server for details.'
    ];
    return json_encode($result);
  }
}

function giveExceptionHandler(Throwable $e) {
  header('Content-Type: application/json');
  $result = [];
  // log error
  error_log($e);
  if (defined('SUPPRESS_SERVER_ERRORS')) {
    // Need to do some conversion
    if ($e instanceof GIVEException) {
      http_response_code(400);
      echo $e->getSuppressedJSON();
    } else {
      http_response_code(500);
      if ($e instanceof mysqli_sql_exception) {
        $result['message'] = "Database error happened. " .
          "Please contact the admin of the server for details.";
      } else {
        $result['message'] = "Server error happened. " .
          "Please contact the admin of the server for details.";
      }
      echo json_encode($result);
    }
  } else {
    // return a 400 error
    if ($e instanceof GIVEException) {
      http_response_code(400);
      echo $e->getJSON();
    } else {
      http_response_code(500);
      $result['message'] = $e->getMessage();
      $result['trace'] = $e->getTrace();
      echo json_encode($result);
    }
  }
}

set_exception_handler('giveExceptionHandler');

function connectCPB($db = 'compbrowser') {
  try {
    $mysqli = new mysqli(CPB_HOST, CPB_USER, CPB_PASS);
    $mysqli->select_db($mysqli->real_escape_string($db));
    return $mysqli;
  } catch (Exception $e) {
    if ($mysqli) {
      $mysqli->close();
    }
    throw $e;
  }
}

function connectCPBWriter($db) {
  try {
    $mysqli = new mysqli(CPB_EDIT_HOST, CPB_EDIT_USER, CPB_EDIT_PASS);
    $mysqli->select_db($mysqli->real_escape_string($db));
    return $mysqli;
  } catch (Exception $e) {
    if ($mysqli) {
      $mysqli->close();
    }
    throw $e;
  }
}

function requestRefHgsID($spc) {
  // this is to request the hgsID from the session (if any)
  // if there is no such thing, then use $_SESSION['ID'] to populate a fake one
  // however, still need to be posted after the real one is generated by UCSC
  if(!isset($_SESSION['hgsIDs'])) {
    $_SESSION['hgsIDs'] = array();
  }
  if(!isset($_SESSION['hgsIDs'][$spc])) {
    $_SESSION['hgsIDs'][$spc] = $_SESSION['ID'] * 10 + 1 + count($_SESSION['hgsIDs']);
  }
  return $_SESSION['hgsIDs'][$spc];
}

function byteSwap32($data) {
  $arr = unpack("V", pack("N", $data));
  return $arr[1];
}

function cmpTwoBits($aHigh, $aLow, $bHigh, $bLow) {
  // return -1 if b < a, 0 if equal, +1 else
  if($aHigh < $bHigh) {
    return 1;
  } else if($aHigh > $bHigh) {
    return -1;
  } else {
    return (($aLow < $bLow)? 1: (($aLow > $bLow)? -1: 0));
  }
}

function rangeIntersection($start1, $end1, $start2, $end2) {
  $s = max($start1, $start2);
  $e = min($end1, $end2);
  return $e - $s;
}

function getRequest() {
  // This function also needs to handle preflight for CORS
  if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    // This is not a CORS preflight request
    if(isset($_SERVER['CONTENT_TYPE'])) {
      switch(explode('; ', strtolower($_SERVER['CONTENT_TYPE']), 2)[0]) {
        case 'application/json':
          return json_decode(file_get_contents('php://input'), true);
        case 'application/x-www-form-urlencoded':
        case 'multipart/form-data':
          return $_REQUEST;
        default:
          error_log('Content-type not recognized: get \'' . $_SERVER['CONTENT_TYPE'] . '\'');
          return file_get_contents('php://input');
      }
    } else {
      return $_REQUEST;
    }
  } else {
    // Insert CORS handling code here.
    // Currently it's handled by Apache
    exit();
  }
}

function var_error_log( $object = NULL ){
    ob_start();                    // start buffer capture
    var_dump( $object );           // dump the values
    error_log(ob_get_contents());// end capture and put stuff to error_log
  ob_end_clean();
}
