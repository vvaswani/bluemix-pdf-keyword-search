<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Client;

// set a long time limit to account 
// for large file uploads and processing time
set_time_limit(6000);

// include autoloader and configuration
require '../vendor/autoload.php';
require '../config.php';

// initialize application
$app = new \Slim\App($config);

// initialize dependency injection container
$container = $app->getContainer();

// add view renderer
$container['view'] = function ($container) {
  return new \Slim\Views\PhpRenderer("../views/");
};

// add PHP Mongo client
$container['db'] = function ($container) {
  $config = $container->get('settings');
  $dbn = substr(parse_url($config['db']['uri'], PHP_URL_PATH), 1);
  $mongo = new MongoClient($config['db']['uri'], array("connectTimeoutMS" => 30000));
  return $mongo->selectDb($dbn);
};

// add Object Storage service client
$container['objectstorage'] = function ($container) {
  $config = $container->get('settings');
  $openstack = new OpenStack\OpenStack(array(
    'authUrl' => $config['object-storage']['url'],
    'region'  => $config['object-storage']['region'],
    'user'    => array(
      'id'       => $config['object-storage']['user'],
      'password' => $config['object-storage']['pass']
  )));
  return $openstack->objectStoreV1();
};

// add Alchemy API client
$container['extractor'] = function ($container) {
  $config = $container->get('settings');
  return new Client(array(
    'base_uri' => 'http://gateway-a.watsonplatform.net/calls/',
    'timeout'  => 6000,
  ));
};

// add Watson document conversion client
$container['converter'] = function ($container) {
  $config = $container->get('settings');
  return new Client(array(
    'base_uri' => 'https://gateway.watsonplatform.net/document-conversion/api/',
    'timeout'  => 6000,
    'auth' => array($config['document-conversion']['user'], $config['document-conversion']['pass']),
    'verify' => false
  ));
};

// index page
$app->get('/', function (Request $request, Response $response) {
   return $response->withStatus(301)->withHeader('Location', 'search');
});

// upload form
$app->get('/add', function (Request $request, Response $response) {
  $response = $this->view->render($response, 'add.phtml', array('router' => $this->router));
  return $response;
})->setName('add');

// upload processor
$app->post('/add', function (Request $request, Response $response) {

  // get configuration
  $config = $this->get('settings');
  

  try {
    // check for valid file upload
    if (empty($_FILES['upload']['name'])) {
      throw new Exception('No file uploaded');
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $type = $finfo->file($_FILES['upload']['tmp_name']);
    if ($type != 'application/pdf') {
      throw new Exception('Invalid file format');    
    }

    // convert uploaded PDF to text
    // connect to Watson document conversion API  
    // transfer uploaded file for conversion to text format
    $apiResponse = $this->converter->post('v1/convert_document?version=2015-12-15', array('multipart' => array(
      array('name' => 'config', 'contents' => '{"conversion_target":"normalized_text"}'),
      array('name' => 'file', 'contents' => fopen($_FILES['upload']['tmp_name'], 'r'))
    )));
    
    // store response
    $text = (string)$apiResponse->getBody();
    unset($apiResponse);

    // extract keywords from text
    // connect to Watson/Alchemy API for keyword extraction 
    // transfer text content for keyword extraction
    // request JSON output
    $apiResponse = $this->extractor->post('text/TextGetRankedKeywords', array('form_params' => array(
      'apikey' => $config['alchemy']['apikey'],
      'text' => strip_tags($text),
      'outputMode' => 'json'
    )));

    // process response
    // create keyword array
    $body = $apiResponse->getBody(); 
    $data = json_decode($body);
    $keywords = array();
    foreach ($data->keywords as $k) {
      $keywords[] = $k->text;
    }
    
    // save keywords to MongoDB
    $collection = $this->db->docs;
    $q = trim($_FILES['upload']['name']);
    $params = $request->getParams();
    $result = $collection->findOne(array('name' => $q));
    $doc = new stdClass;
    if (count($result) > 0) {
      $doc->_id = $result['_id'];
    }
    $doc->name = trim($_FILES['upload']['name']);
    $doc->keywords = $keywords;
    $doc->description = trim(strip_tags($params['description']));
    $doc->updated = time();
    $collection->save($doc);
    
    // save PDF to object storage
    $service = $this->objectstorage;
    $containers = $service->listContainers();
    
    $containerExists = false;
    foreach($containers as $c) {
      if ($c->name == 'documents') {
        $containerExists = true;
        break;
      }
    }
    
    if ($containerExists == false) {
      $container = $service->createContainer(array(
        'name' => 'documents'
      )); 
    } else {    
      $container = $service->getContainer('documents');
    }
      
    $stream = new Stream(fopen($_FILES['upload']['tmp_name'], 'r'));
    $options = array(
      'name'   => trim($_FILES['upload']['name']),
      'stream' => $stream,
    );
    $container->createObject($options);

    $response = $this->view->render($response, 'add.phtml', array('keywords' => $keywords, 'object' => trim($_FILES['upload']['name']), 'router' => $this->router));
    return $response;
    
  } catch (ClientException $e) {
    // in case of a Guzzle exception
    // display HTTP response content
    throw new Exception($e->getResponse());
  }

});

$app->get('/search', function (Request $request, Response $response) {
  $params = $request->getQueryParams();
  $results = array();
  if (isset($params['q'])) {
    $q = trim(strip_tags($params['q']));
    if (!empty($q)) {
      $where = array(
        '$text' => array('$search' => $q) 
      );  
    }
    $collection = $this->db->docs;
    $results = $collection->find($where)->sort(array('updated' => -1));    
  }
  $response = $this->view->render($response, 'search.phtml', array('router' => $this->router, 'results' => $results));
  return $response;
})->setName('search');

$app->get('/download/{id}', function (Request $request, Response $response, $args) {
  $service = $this->objectstorage;
  $stream = $service->getContainer('documents')
                  ->getObject(trim(strip_tags($args['id'])))
                  ->download();
  $response = $response->withHeader('Content-type', 'application/pdf')
                       ->withHeader('Content-Disposition', 'attachment; filename="' . trim(strip_tags($args['id'])) .'"')
                       ->withHeader('Content-Length', $stream->getSize())
                       ->withHeader('Expires', '@0')
                       ->withHeader('Cache-Control', 'must-revalidate')
                       ->withHeader('Pragma', 'public');
  $response = $response->withBody($stream);
  return $response;
})->setName('download');

$app->get('/legal', function (Request $request, Response $response) {
  $response = $this->view->render($response, 'legal.phtml', array('router' => $this->router));
  return $response;
})->setName('legal');

$app->get('/reset', function (Request $request, Response $response) {
  $collection = $this->db->docs;
  $collection->remove();    
  $service = $this->objectstorage;
  $container = $service->getContainer('documents');
  foreach ($container->listObjects() as $object) {
    $object->containerName = 'documents';
    $object->delete();
  }
   return $response->withStatus(301)->withHeader('Location', 'search');
})->setName('reset');


$app->run();

