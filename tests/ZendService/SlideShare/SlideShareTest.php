<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendServiceTest\SlideShare;

use Laminas\Http\Client as HttpClient;
use ZendService\SlideShare;
use ZendService\SlideShare\SlideShare as SlideShareService;
use Laminas\Cache\StorageFactory as CacheFactory;
use Laminas\Cache\Storage\Adapter\AdapterInterface as CacheAdapter;

/**
 * @category   Zend
 * @package    Zend_Service_SlideShare
 * @subpackage UnitTests
 * @group      Zend_Service
 * @group      Zend_Service_SlideShare
 */
class SlideShareTest extends \PHPUnit_Framework_TestCase
{
    /**
     * The Slide share object instance
     *
     * @var \ZendService\SlideShare
     */
    protected static $_ss;

    /**
     * Enter description here...
     *
     * @return \ZendService\SlideShare\SlideShare
     */
    protected function _getSSObject()
    {
        $ss = new SlideShareService(TESTS_ZEND_SERVICE_SLIDESHARE_APIKEY,
                                    TESTS_ZEND_SERVICE_SLIDESHARE_SHAREDSECRET,
                                    TESTS_ZEND_SERVICE_SLIDESHARE_USERNAME,
                                    TESTS_ZEND_SERVICE_SLIDESHARE_PASSWORD,
                                    new HttpClient(null, array(
                                        'maxredirects'  => 2, 
                                        'timeout'       => 5, 
                                        'sslverifypeer' => false
                                    )));

        $cache = CacheFactory::adapterFactory('memory', array('memory_limit' => 0));
        $ss->setCacheObject($cache);

        return $ss;
    }

    public function setUp()
    {
        if(!defined("TESTS_ZEND_SERVICE_SLIDESHARE_APIKEY") ||
           !defined("TESTS_ZEND_SERVICE_SLIDESHARE_SHAREDSECRET") ||
           !defined("TESTS_ZEND_SERVICE_SLIDESHARE_USERNAME") ||
           !defined("TESTS_ZEND_SERVICE_SLIDESHARE_PASSWORD") ||
           (TESTS_ZEND_SERVICE_SLIDESHARE_APIKEY == "") ||
           (TESTS_ZEND_SERVICE_SLIDESHARE_SHAREDSECRET == "") ||
           (TESTS_ZEND_SERVICE_SLIDESHARE_USERNAME == "") ||
           (TESTS_ZEND_SERVICE_SLIDESHARE_PASSWORD == "")) {

               $this->markTestSkipped("You must configure an account for slideshare to run these tests");
        }
    }

    public function tearDown()
    {
    }

    public function testGetSlideShow()
    {
        if(!defined("TESTS_ZEND_SERVICE_SLIDESHARE_SLIDESHOWID") ||
           (TESTS_ZEND_SERVICE_SLIDESHARE_SLIDESHOWID <= 0)) {
               $this->markTestSkipped("You must provide a Slideshow ID to retrieve to perform this test");
        }

        $ss = $this->_getSSObject();
        try {
            $result = $ss->getSlideShow(TESTS_ZEND_SERVICE_SLIDESHARE_SLIDESHOWID);
        } catch(Exception $e) {
            $this->fail("Exception Caught retrieving Slideshow");
        }

        $this->assertTrue($result instanceof SlideShare\SlideShow);

    }

    public function testGetSlideShowByTag()
    {
        $ss = $this->_getSSObject();

        try {
            $results = $ss->getSlideShowsByTag('zend', 0, 1);
        } catch(Exception $e) {
            $this->fail("Exception Caught retrieving Slideshow List (tag)");
        }

        $this->assertTrue(is_array($results));
        $this->assertTrue(count($results) == 1);
        $this->assertTrue($results[0] instanceof SlideShare\SlideShow);

    }

    public function testGetSlideShowByTags()
    {
        $ss = $this->_getSSObject();

        try {
            $results = $ss->getSlideShowsByTag(array('zend', 'php'), 0, 1);
        } catch(Exception $e) {
            $this->fail("Exception Caught retrieving Slideshow List (tag)");
        }

        $this->assertTrue(is_array($results));

        if(!empty($results)) {
            $this->assertTrue(count($results) == 1);
            $this->assertTrue($results[0] instanceof SlideShare\SlideShow);
        }
    }

    public function testGetSlideShowByUsername()
    {
        $ss = $this->_getSSObject();

        try {
            $results = $ss->getSlideShowsByUsername(TESTS_ZEND_SERVICE_SLIDESHARE_USERNAME, 0, 1);
        } catch(Exception $e) {
            $this->fail("Exception Caught retrieving Slideshow List (tag)");
        }
        

        $this->assertTrue(is_array($results));
        $this->assertTrue(count($results) == 1);
        $this->assertTrue($results[0] instanceof SlideShare\SlideShow);

    }


    public function testUploadSlideShowInvalidFileException()
    {
        $this->setExpectedException('\ZendService\SlideShare\Exception\InvalidArgumentException',
                    'Specified Slideshow for upload not found or unreadable');

        $ss = $this->_getSSObject();
        $show = new SlideShare\SlideShow();
        $show->setFilename('invalid_filename');
        $show->setDescription('Unit Test');
        $show->setTitle('title');
        $show->setTags(array('unittest'));
        $show->setID(0);

        $result = $ss->uploadSlideShow($show, false);
    }

    public function testUploadSlideShow()
    {
        $ss = $this->_getSSObject();

        $title = "Unit Test for ZF SlideShare Component";
        $ppt_file = __DIR__."/_files/demo.ppt";

        $show = new SlideShare\SlideShow();
        $show->setFilename($ppt_file);
        $show->setDescription("Unit Test");
        $show->setTitle($title);
        $show->setTags(array('unittest'));
        $show->setID(0);

        try {
            $result = $ss->uploadSlideShow($show, false);
        } catch(Exception $e) {

            if($e->getCode() == SlideShareService::SERVICE_ERROR_NOT_SOURCEOBJ) {
                // We ignore this exception, the web service sometimes throws this
                // error code because it seems to be buggy. Unfortunately it seems
                // to be sparatic so we can't code around it and have to call this
                // test a success
                return;
            } else {
                $this->fail("Exception Caught uploading slideshow");
            }
        }

        $this->assertTrue($result instanceof SlideShare\SlideShow);
        $this->assertTrue($result->getId() > 0);
        $this->assertTrue($result->getTitle() === $title);

    }

    public function testSlideShowObj()
    {
        $ss = new SlideShare\SlideShow();

        $ss->setDescription("Foo");
        $ss->setEmbedCode("Bar");
        $ss->setFilename("Baz");
        $ss->setId(123);
        $ss->setLocation("Somewhere");
        $ss->setNumViews(4432);
        $ss->setPermaLink("nowhere");
        $ss->setStatus(124);
        $ss->setStatusDescription("Boo");
        $ss->setTags(array('bar', 'baz'));
        $ss->addTag('fon');
        $ss->setThumbnailUrl('asdf');
        $ss->setTitle('title');

        $this->assertEquals($ss->getDescription(), "Foo");
        $this->assertEquals($ss->getEmbedCode(), "Bar");
        $this->assertEquals($ss->getFilename(), "Baz");
        $this->assertEquals($ss->getId(), 123);
        $this->assertEquals($ss->getLocation(), "Somewhere");
        $this->assertEquals($ss->getNumViews(), 4432);
        $this->assertEquals($ss->getPermaLink(), "nowhere");
        $this->assertEquals($ss->getStatus(), 124);
        $this->assertEquals($ss->getStatusDescription(), "Boo");
        $this->assertEquals($ss->getTags(), array('bar', 'baz', 'fon'));
        $this->assertEquals($ss->getThumbnailUrl(), "asdf");
        $this->assertEquals($ss->getTitle(), "title");

    }

    /**
     * @group   ZF-3247
     */
    public function testSlideShareObjectHandlesUnicodeCharactersWell()
    {
        $slideShow = new SlideShare\SlideShow();

        $slideShow->setTitle('Unicode test: ஸ்றீனிவாஸ ராமானுஜன் ஐயங்கார்');

        if (!extension_loaded('mbstring')) {
            $this->markTestSkipped('Extension "mbstring" not loaded');
        }
        $this->assertEquals('UTF-8', mb_detect_encoding($slideShow->getTitle()));
    }
}
