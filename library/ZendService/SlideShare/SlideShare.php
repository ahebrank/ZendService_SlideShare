<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendService\SlideShare;

use SimpleXMLElement;
use Laminas\Cache\StorageFactory as CacheFactory;
use Laminas\Cache\Storage\StorageInterface as CacheStorage;
use Laminas\Http\Client as HttpClient;
use Laminas\Http\Exception as HttpException;
use Laminas\Http\Request as HttpRequest;
use Laminas\Xml\Security as XmlSecurity;

/**
 * The ZendService\SlideShare component is used to interface with the
 * slideshare.net web server to retrieve slide shows hosted on the web site for
 * display or other processing.
 *
 * @category   Zend
 * @package    Zend_Service
 * @subpackage SlideShare
 * @throws     ZendService\SlideShare\Exception
 */
class SlideShare
{
    /**
     * Web service result code mapping
     */
    const SERVICE_ERROR_BAD_APIKEY       = 1;
    const SERVICE_ERROR_BAD_AUTH         = 2;
    const SERVICE_ERROR_MISSING_TITLE    = 3;
    const SERVICE_ERROR_MISSING_FILE     = 4;
    const SERVICE_ERROR_EMPTY_TITLE      = 5;
    const SERVICE_ERROR_NOT_SOURCEOBJ    = 6;
    const SERVICE_ERROR_INVALID_EXT      = 7;
    const SERVICE_ERROR_FILE_TOO_BIG     = 8;
    const SERVICE_ERROR_SHOW_NOT_FOUND   = 9;
    const SERVICE_ERROR_USER_NOT_FOUND   = 10;
    const SERVICE_ERROR_GROUP_NOT_FOUND  = 11;
    const SERVICE_ERROR_MISSING_TAG      = 12;
    const SERVICE_ERROR_DAILY_LIMIT      = 99;
    const SERVICE_ERROR_ACCOUNT_BLOCKED  = 100;

    /**
     * Slide share Web service communication URIs
     */
    const SERVICE_UPLOAD_URI                  = 'https://www.slideshare.net/api/2/upload_slideshow';
    const SERVICE_GET_SHOW_URI                = 'https://www.slideshare.net/api/2/get_slideshow';
    const SERVICE_GET_SHOW_BY_USER_URI        = 'https://www.slideshare.net/api/2/get_slideshows_by_user';
    const SERVICE_GET_SHOW_BY_TAG_URI         = 'https://www.slideshare.net/api/2/get_slideshows_by_tag';
    const SERVICE_GET_SHOW_BY_GROUP_URI       = 'https://www.slideshare.net/api/2/get_slideshows_by_group';
    const SERVICE_SEARCH_SLIDESHOWS_URI       = 'https://www.slideshare.net/api/2/search_slideshows';

    /**
     * The MIME type of Slideshow files
     *
     */
    const POWERPOINT_MIME_TYPE    = "application/vnd.ms-powerpoint";

    /**
     * The API key to use in requests
     *
     * @var string The API key
     */
    protected $apiKey;

    /**
     * The shared secret to use in requests
     *
     * @var string the Shared secret
     */
    protected $sharedSecret;

    /**
     * The username to use in requests
     *
     * @var string the username
     */
    protected $username;

    /**
     * The password to use in requests
     *
     * @var string the password
     */
    protected $password;

    /**
     * The HTTP Client object to use to perform requests
     *
     * @var Laminas\Http\Client
     */
    protected $httpClient;

    /**
     * The Cache object to use to perform caching
     *
     * @var CacheStorage
     */
    protected $cacheobject;

    /**
     * The Constructor
     *
     * @param string $apikey The API key
     * @param string $sharedSecret The shared secret
     * @param string $username The username
     * @param string $password The password
     */
    public function __construct($apikey, $sharedSecret, $username = null, $password = null, HttpClient $httpClient = null)
    {
        $this->setApiKey($apikey)
            ->setSharedSecret($sharedSecret)
            ->setUserName($username)
            ->setPassword($password);

        $this->setHttpClient($httpClient ?: new HttpClient(null, array('maxredirects' => 2, 'timeout' => 5)));
    }

    /**
     * Sets the Laminas\Http\Client object to use in requests. If not provided a default will
     * be used.
     *
     * @param Laminas\Http\Client $client The HTTP client instance to use
     * @return ZendService\SlideShare\SlideShare
     */
    public function setHttpClient(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * Returns the instance of the Laminas\Http\Client which will be used. Creates an instance
     * of Laminas\Http\Client if no previous client was set.
     *
     * @return HttpClient The HTTP client which will be used
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * Sets the CacheStorage object to use to cache the results of API queries
     *
     * @param  CacheStorage $cacheobject The CacheStorage object used
     * @return ZendService\SlideShare\SlideShare
     */
    public function setCacheObject(CacheStorage $cacheobject)
    {
        $this->cacheobject = $cacheobject;
        return $this;
    }

    /**
     * Gets the CacheStorage object which will be used to cache API queries. If no cache object
     * was previously set the the default will be used (Filesystem caching in /tmp with a life
     * time of 43200 seconds)
     *
     * @return CacheStorage The object used in caching
     */
    public function getCacheObject()
    {

        if (!($this->cacheobject instanceof CacheStorage)) {
            $cache = CacheFactory::factory(array(
                'adapter' => array(
                    'name' => 'filesystem',
                    'options' => array(
                        'ttl' => 43200,
                    )
                ),
                'plugins' => array(
                    array(
                        'name'    => 'serializer',
                        'options' => array(
                            'serializer' => 'PhpSerialize',
                        ),
                    )
                ),
            ));

            $this->setCacheObject($cache);
        }

        return $this->cacheobject;
    }

    /**
     * Returns the user name used for API calls
     *
     * @return string The username
     */
    public function getUserName()
    {
        return $this->username;
    }

    /**
     * Sets the user name to use for API calls
     *
     * @param string $un The username to use
     * @return ZendService\SlideShare\SlideShare
     */
    public function setUserName($un)
    {
        $this->username = $un;
        return $this;
    }

    /**
     * Gets the password to use in API calls
     *
     * @return string the password to use in API calls
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Sets the password to use in API calls
     *
     * @param string $pw The password to use
     * @return ZendService\SlideShare\SlideShare
     */
    public function setPassword($pw)
    {
        $this->password = (string) $pw;
        return $this;
    }

    /**
     * Gets the API key to be used in making API calls
     *
     * @return string the API Key
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Sets the API key to be used in making API calls
     *
     * @param string $key The API key to use
     * @return ZendService\SlideShare\SlideShare
     */
    public function setApiKey($key)
    {
        $this->apiKey = (string) $key;
        return $this;
    }

    /**
     * Gets the shared secret used in making API calls
     *
     * @return string the Shared secret
     */
    public function getSharedSecret()
    {
        return $this->sharedSecret;
    }

    /**
     * Sets the shared secret used in making API calls
     *
     * @param string $secret the shared secret
     * @return ZendService\SlideShare\SlideShare
     */
    public function setSharedSecret($secret)
    {
        $this->sharedSecret = (string) $secret;
        return $this;
    }

    /**
     * Uploads the specified Slide show the the server
     *
     * @param ZendService\SlideShare\SlideShow $ss The slide show object representing the slide show to upload
     * @param boolean $makeSourcePublic Determines if the slide show's source file is public or not upon upload
     * @throws \ZendService\SlideShare\Exception
     * @return ZendService\SlideShare\SlideShow The passed Slide show object, with the new assigned ID provided
     */
    public function uploadSlideShow(SlideShow $ss, $makeSourcePublic = true)
    {
        $timestamp = time();

        $params = array(
            'api_key'         => $this->getApiKey(),
            'ts'              => $timestamp,
            'hash'            => sha1($this->getSharedSecret() . $timestamp),
            'username'        => $this->getUserName(),
            'password'        => $this->getPassword(),
            'slideshow_title' => $ss->getTitle(),
            'make_src_public' => ($makeSourcePublic ? 'Y' : 'N'),
        );

        $description = $ss->getDescription();
        $tags = $ss->getTags();

        $filename = $ss->getFilename();

        if (!file_exists($filename) || !is_readable($filename)) {
            throw new Exception\InvalidArgumentException("Specified Slideshow for upload not found or unreadable");
        }

        if (!empty($description)) {
            $params['slideshow_description'] = $description;
        } else {
            $params['slideshow_description'] = "";
        }

        if (!empty($tags)) {
            $tmp = array();
            foreach($tags as $tag) {
                $tmp[] = "\"$tag\"";
            }
            $params['slideshow_tags'] = implode(' ', $tmp);
        } else {
            $params['slideshow_tags'] = "";
        }

        $httpClient = $this->getHttpClient();

        $request = new HttpRequest;
        $request->setUri(self::SERVICE_UPLOAD_URI);
        $request->getPost()->fromArray($params);
        $request->setMethod(HttpRequest::METHOD_POST);
        $httpClient->setEncType(HttpClient::ENC_URLENCODED);
        $httpClient->setRequest($request);
        $httpClient->setFileUpload($filename, 'slideshow_srcfile');

        try {
            $response = $httpClient->send();
        } catch(HttpException\ExceptionInterface $e) {
            throw new HttpException\RuntimeException("Service Request Failed: {$e->getMessage()}", 0, $e);
        }

        $sxe = XmlSecurity::scan($response->getBody());

        if ($sxe->getName() == "SlideShareServiceError") {
            $message = (string) $sxe->Message[0];
            if (strpos(':', $message) !== false) {
              list($code, $error_str) = explode(':', $message);
            }
            else {
              $error_str = $message;
              $code = null;
            }
            throw new Exception\RuntimeException(trim($error_str), $code);
        }

        if (!$sxe->getName() == "SlideShowUploaded") {
            throw new Exception\RuntimeException("Unknown XML Respons Received");
        }

        $ss->setId((int) (string) $sxe->SlideShowID);

        return $ss;
    }

    /**
     * Retrieves a slide show's information based on slide show URL.
     *
     * @param string $slideshow_url The slide show URL
     * @throws Exception
     * @return SlideShow the Slideshow object
     */
    public function getSlideShowByUrl($slideshow_url)
    {
      return $this->retrieveSlideShowByQuery('slideshow_url', $slideshow_url);
    }

    /**
     * Retrieves a slide show's information based on slide show ID
     *
     * @param int $ss_id The slide show ID
     * @throws Exception
     * @return SlideShow the Slideshow object
     */
    public function getSlideShow($ss_id)
    {
      return $this->retrieveSlideShowByQuery('slideshow_id', $ss_id);
    }

    /**
     * Retrieves a slide show's informations based on a parameter
     *
     * @param string $name
     *   The name of the parameter used to retrieve the slideshow.
     *   Can be either slideshow_id or slideshow_url.
     * @param mixed $value
     *   The value of the parameter.
     * @throws Exception
     * @return SlideShow
     *   The SlideShow objet.
     */
    protected function retrieveSlideShowByQuery($name, $value)
    {
        $timestamp = time();

        $params = array(
            'api_key'       => $this->getApiKey(),
            'ts'            => $timestamp,
            'hash'          => sha1($this->getSharedSecret() . $timestamp),
            $name           => $value,
            'detailed'      => 1,
        );

        $cache = $this->getCacheObject();

        $cache_key = md5("__zendslideshare_cache_ss_{$name}_{$value}");

        if (!$retval = $cache->getItem($cache_key)) {

            $httpClient = $this->getHttpClient();

            $request = new HttpRequest;
            $request->setUri(self::SERVICE_GET_SHOW_URI);
            $request->getPost()->fromArray($params);
            $request->setMethod(HttpRequest::METHOD_POST);
            $httpClient->setEncType(HttpClient::ENC_URLENCODED);

            try {
                $response = $httpClient->send($request);
            } catch(HttpException\ExceptionInterface $e) {
                throw new HttpException\RuntimeException("Service Request Failed: {$e->getMessage()}", 0, $e);
            }

            $sxe = XmlSecurity::scan($response->getBody());

            if ($sxe->getName() == "SlideShareServiceError") {
                $message = (string) $sxe->Message[0];
                if (strpos(':', $message) !== FALSE) {
                  list($code, $error_str) = explode(':', $message);
                }
                else {
                  $error_str = $message;
                  $code = NULL;
                }
                throw new Exception\RuntimeException(trim($error_str), $code);
            }

            if (!$sxe->getName() == 'Slideshows') {
                throw new Exception\RuntimeException('Unknown XML Response Received');
            }

            $retval = $this->slideShowNodeToObject(clone $sxe);

            $cache->setItem($cache_key, $retval);
        }

        return $retval;
    }

    /**
     * Retrieves an array of slide shows for a given username
     *
     * @param string $username The username to retrieve slide shows from
     * @param int $offset The offset of the list to start retrieving from
     * @param int $limit The maximum number of slide shows to retrieve
     * @return array An array of ZendService\SlideShare\SlideShow objects
     */
    public function getSlideShowsByUsername($username, $offset = null, $limit = null)
    {
        return $this->getSlideShowsByType('username_for', $username, $offset, $limit);
    }

    /**
     * Retrieves an array of slide shows based on tag
     *
     * @param string|array $tag The tag to retrieve slide shows with
     * @param int $offset The offset of the list to start retrieving from
     * @param int $limit The maximum number of slide shows to retrieve
     * @return array An array of SlideShow objects
     */
    public function getSlideShowsByTag($tag, $offset = null, $limit = null)
    {
        if (is_array($tag)) {
            $tmp = array();
            foreach($tag as $t) {
                $tmp[] = "\"$t\"";
            }

            $tag = implode(" ", $tmp);
        }

        return $this->getSlideShowsByType('tag', $tag, $offset, $limit);
    }

    /**
     * Retrieves an array of slide shows based on group name
     *
     * @param string $group The group name to retrieve slide shows for
     * @param int $offset The offset of the list to start retrieving from
     * @param int $limit The maximum number of slide shows to retrieve
     * @return array An array of SlideShow objects
     */
    public function getSlideShowsByGroup($group, $offset = null, $limit = null)
    {
        return $this->getSlideShowsByType('group_name', $group, $offset, $limit);
    }

    /**
     * Retrieves SlideShow object arrays based on the type of
     * list desired
     *
     * @param string $key The type of slide show object to retrieve
     * @param string $value The specific search query for the slide show type to look up
     * @param int $offset The offset of the list to start retrieving from
     * @param int $limit The maximum number of slide shows to retrieve
     * @throws Exception
     * @return array An array of SlideShow objects
     */
    protected function getSlideShowsByType($key, $value, $offset = null, $limit = null)
    {
        $key = strtolower($key);

        switch($key) {
            case 'username_for':
                $responseTag = 'User';
                $queryUri = self::SERVICE_GET_SHOW_BY_USER_URI;
                break;
            case 'group_name':
                $responseTag = 'Group';
                $queryUri = self::SERVICE_GET_SHOW_BY_GROUP_URI;
                break;
            case 'tag':
                $responseTag = 'Tag';
                $queryUri = self::SERVICE_GET_SHOW_BY_TAG_URI;
                break;
            default:
                throw new Exception\RuntimeException("Invalid SlideShare Query");
        }

        $timestamp = time();

        $params = array(
            'api_key'   => $this->getApiKey(),
            'ts'        => $timestamp,
            'hash'      => sha1($this->getSharedSecret() . $timestamp),
            $key        => $value,
            'detailed'  => 1,
        );

        if ($offset !== null) {
            $params['offset'] = (int)$offset;
        }

        if ($limit !== null) {
            $params['limit'] = (int)$limit;
        }

        $cache = $this->getCacheObject();

        $cache_key = md5('__zendslideshare_cache_' . $key . $value . $offset . $limit);

        if (!$retval = $cache->getItem($cache_key)) {

            $httpClient = $this->getHttpClient();

            $request = new HttpRequest;
            $request->setUri($queryUri);
            $request->getPost()->fromArray($params);
            $request->setMethod(HttpRequest::METHOD_POST);
            $httpClient->setEncType(HttpClient::ENC_URLENCODED);

            try {
                $response = $httpClient->send($request);
            } catch(HttpException\ExceptionInterface $e) {
                throw new HttpException\RuntimeException("Service Request Failed: {$e->getMessage()}", 0, $e);
            }

            $sxe = XmlSecurity::scan($response->getBody());

            if ($sxe->getName() == "SlideShareServiceError") {
                $message = (string) $sxe->Message[0];
                if (strpos(':', $message) !== false) {
                  list($code, $error_str) = explode(':', $message);
                }
                else {
                  $error_str = $message;
                  $code = null;
                }
                throw new Exception\RuntimeException(trim($error_str), $code);
            }

            if (!$sxe->getName() == $responseTag) {
                throw new Exception\RuntimeException('Unknown or Invalid XML Response Received');
            }

            $retval = array();

            foreach($sxe->children() as $node) {
                if ($node->getName() == 'Slideshow') {
                    $retval[] = $this->slideShowNodeToObject($node);
                }
            }

            $cache->setItem($cache_key, $retval);
        }

        return $retval;
    }

    /**
     * Retrieves SlideShow object arrays based on the search query
     *
     * @param string $query The query string
     * @throws Exception
     * @return array An array of SlideShow objects
     */
    public function searchSlideShows($query)
    {
        $timestamp = time();

        $params = array(
            'api_key'   => $this->getApiKey(),
            'ts'        => $timestamp,
            'hash'      => sha1($this->getSharedSecret() . $timestamp),
            'q'         => (string) $query,
            'detailed'  => 1,
        );

        $cache = $this->getCacheObject();

        $cache_key = md5('__zendslideshare_cache_search_' . $query);

        if (!$retval = $cache->getItem($cache_key)) {

            $httpClient = $this->getHttpClient();

            $request = new HttpRequest;
            $request->setUri(self::SERVICE_SEARCH_SLIDESHOWS_URI);
            $request->getPost()->fromArray($params);
            $request->setMethod(HttpRequest::METHOD_POST);
            $httpClient->setEncType(HttpClient::ENC_URLENCODED);

            try {
                $response = $httpClient->send($request);
            } catch(HttpException\ExceptionInterface $e) {
                throw new HttpException\RuntimeException("Service Request Failed: {$e->getMessage()}", 0, $e);
            }

            $sxe = XmlSecurity::scan($response->getBody());

            if ($sxe->getName() == "SlideShareServiceError") {
                $message = (string) $sxe->Message[0];
                if (strpos(':', $message) !== false) {
                  list($code, $error_str) = explode(':', $message);
                }
                else {
                  $error_str = $message;
                  $code = null;
                }
                throw new Exception\RuntimeException(trim($error_str), $code);
            }

            if (!$sxe->getName() == 'Slideshows') {
                throw new Exception\RuntimeException('Unknown or Invalid XML Response Received');
            }

            $retval = array();

            foreach($sxe->children() as $node) {
                if ($node->getName() == 'Slideshow') {
                    $retval[] = $this->slideShowNodeToObject($node);
                }
            }

            $cache->setItem($cache_key, $retval);
        }

        return $retval;
    }

    /**
     * Converts a SimpleXMLElement object representing a response from the service
     * into a SlideShow object
     *
     * @param SimpleXMLElement $node The input XML from the slideshare.net service
     * @throws Exception\RuntimeException
     * @return SlideShow The resulting object
     */
    protected function slideShowNodeToObject(SimpleXMLElement $node)
    {
        if ($node->getName() != 'Slideshow') {
            throw new Exception\RuntimeException("Was not provided the expected XML Node for processing");
        }

        $ss = new SlideShow();

        $ss->setId((string) $node->ID);
        $ss->setDescription((string) $node->Description);
        $ss->setEmbedCode((string) $node->Embed);

        $ss->setNumViews((string) $node->NumViews);
        $ss->setNumDownloads((string) $node->NumDownloads);
        $ss->setNumComments((string) $node->NumComments);
        $ss->setNumFavorites((string) $node->NumFavorites);
        $ss->setNumSlides((string) $node->NumSlides);

        $ss->setPermaLink((string) $node->URL);
        $ss->setStatus((string) $node->Status);
        $ss->setStatusDescription((string) $node->StatusDescription);

        foreach($node->Tags->Tag as $tag) {
            if (!in_array($tag, $ss->getTags())) {
                $ss->addTag($tag);
            }
        }

        $ss->setThumbnailUrl((string) $node->ThumbnailURL);
        $ss->setThumbnailSmallUrl((string) $node->ThumbnailSmallURL);
        $ss->setTitle((string) $node->Title);
        $ss->setLocation((string) $node->PPTLocation);

        $ss->setUsername((string) $node->Username);
        $ss->setCreated((string) $node->Created);
        $ss->setUpdated((string) $node->Updated);
        $ss->setLanguage((string) $node->Language);
        $ss->setFormat((string) $node->Format);
        $ss->setDownload((string) $node->Download);
        $ss->setDownloadUrl((string) $node->DownloadUrl);

        $ss->setSlideshowEmbedUrl((string) $node->SlideshowEmbedUrl);

        foreach($node->RelatedSlideshows->RelatedSlideshowID as $id) {
            if (!in_array($id, $ss->getRelatedSlideshowIds())) {
                $ss->addRelatedSlideshowId($id);
            }
        }

        return $ss;
    }
}
