<?php

require_once '../minjs/minjs.php';

class BasicTest extends PHPUnit_Framework_TestCase
{
    protected $minjs;
    
    protected function setUp()
    {   
        global $minjs;
        $this->minjs = $minjs;
    }
    
    public function testPageListIsArray()
    {
        $menu = $this->minjs->query('values','pages.list');
        $this->assertNotEmpty($menu);
        $this->assertInternalType('array',$menu);
        $this->assertGreaterThanOrEqual(2,count($menu));
    }
    
    public function testHomePageRetrieval()
    {
        $page = 'home';
        $page = $this->minjs->query('record','pages.find',array('name'=>$page));
        $this->assertNotEmpty($page);
        $this->assertInternalType('array',$page);
        $this->assertNotEmpty($page['data']);
        $this->assertInternalType('string',$page['data']);
        $this->assertGreaterThanOrEqual(10,strlen($page['data']));
    }
    
    public function testLoginSuccess()
    {
        $username = $password = 'admin';
        $user = $this->minjs->query('record','users.login',compact('username','password'));
        $this->assertNotEmpty($user);
        $this->assertInternalType('array',$user);
        $this->assertInternalType('string',$user['username']);
        $this->assertEquals('admin',$user['username']);
    }
    
    public function testLoginFailure()
    {
        $username = 'admin';
        $password = 'secret';
        $user = $this->minjs->query('record','users.login',compact('username','password'));
        $this->assertEmpty($user);
    }
    
}