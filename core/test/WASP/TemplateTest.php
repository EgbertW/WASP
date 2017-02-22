<?php
/*
This is part of WASP, the Web Application Software Platform.
It is published under the MIT Open Source License.

Copyright 2017, Egbert van der Wal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace WASP;

use PHPUnit\Framework\TestCase;
use WASP\Http\Error as HttpError;
use WASP\Http\Request;

/**
 * @covers WASP\Template
 */
final class TemplateTest extends TestCase
{
    private $request;
    private $resolver;

    public function setUp()
    {
        $this->request = new MockTemplateRequest;
        $this->resolver = System::getInstance()->resolver();
    }

    /**
     * @covers WASP\Template::__construct
     * @covers WASP\Template::assign
     * @covers WASP\Template::setTitle
     * @covers WASP\Template::title
     */
    public function testConstruct()
    {
        $tpl = new Template($this->request);
        $tpl->setTemplate('error/HttpError');
        $tpl->setTitle('IO Error');
        $tpl->assign('exception', new IOException('Fail'));

        $this->assertEquals('IO Error', $tpl->title());
    }

    public function testExisting()
    {
        $file = $this->resolver->template('error/HttpError');
        $tpl = new Template($this->request);
        $tpl->setTemplate($file);

        $this->assertEquals($file, $tpl->getTemplate());

        $this->expectException(HttpError::class);
        $this->expectExceptionMessage("Template file could not be found");
        $this->expectExceptionCode(500);

        $tpl->setTemplate('/foo/bar/baz');
    }

    public function testNoTitle()
    {
        $tpl = new Template($this->request);

        $this->assertEquals('foobar - /', $tpl->title());
    }

    public function testNoTitleNoSiteName()
    {
        $tpl = new Template($this->request);

        $s = $this->request->vhost->getSite();
        $s->setName('default');
        $this->assertEquals('/', $tpl->title());
    }

    public function testWants()
    {
        $tpl = new Template($this->request);

        $this->request->accept = Request::parseAccept('text/html;q=1,text/plain;q=0.9');
        $this->assertFalse($tpl->wantJSON());
        $this->assertTrue($tpl->wantHTML() !== false);
        $this->assertTrue($tpl->wantText() !== false);
        $this->assertFalse($tpl->wantXML());

        $this->request->accept = Request::parseAccept('application/json;q=1,application/*;q=0.9');
        $this->assertTrue($tpl->wantJSON() !== false);
        $this->assertFalse($tpl->wantHTML());
        $this->assertFalse($tpl->wantText());
        $this->assertTrue($tpl->wantXML() !== false);

        $this->request->accept = Request::parseAccept('application/json;q=1,text/html;q=0.9,text/plain;q=0.8');
        $type = $tpl->chooseResponse(array('application/json', 'text/html'));
        $this->assertEquals('application/json', $type);

        $type = $tpl->chooseResponse(array('text/plain', 'text/html'));
        $this->assertEquals('text/html', $type);
    }

    public function testAssets()
    {
        $tpl = new Template($this->request);
        $tpl->addJS('test');
        $tpl->addCSS('test');

        $js_str = $tpl->insertJS();
        $css_str = $tpl->insertCSS();

        $this->assertEquals('#WASP-JAVASCRIPT#', $js_str);
        $this->assertEquals('#WASP-CSS#', $css_str);
    }

    public function testSetExceptionTemplate()
    {
        $tpl = new Template($this->request);

        $resolve = System::getInstance()->resolver(); 
        $file = $resolve->template('error/HttpError');

        $tpl->setExceptionTemplate(new HttpError(500, 'Foobarred'));
        $this->assertEquals($file, $tpl->getTemplate());

        $tpl->setExceptionTemplate(new MockTemplateHttpError(500, 'Foobarred'));
        $this->assertEquals($file, $tpl->getTemplate());
    }

    public function testTranslations()
    {
        $tpl = new Template($this->request);
        $this->request->setTemplate($tpl);

        I18n\Translate::setLanguage('en');
        $tpl->setLanguageOrder('en', 'nl');
        $res = \tl('foo', 'bar');
        $this->assertEquals('foo', $res);
    }
}

class MockTemplateRequest extends Request
{
    public function __construct()
    {
        $this->resolver = System::getInstance()->resolver(); 
        $this->route = '/';
        $this->vhost = new MockTemplateVhost();
        $this->response_builder = new Http\ResponseBuilder($this);
        $this->config = new Dictionary();
    }

    public function setTemplate($tpl)
    {
        $this->template = $tpl;
    }
}

class MockTemplateVhost extends VirtualHost
{
    public function __construct()
    {}

    public function getSite()
    {
        if ($this->site === null)
        {
            $this->site = new Site;
            $this->site->setName('foobar');
        }
        return $this->site;
    }

}

class MockTemplateHttpError extends HttpError
{
}
