<?php
/**
* MIT License
* 
* Copyright (c) 2026 Ákos Nikházy
* 
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
* 
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
* 
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
* 
* index.php
*
* Entry point for the application.
* - require head.php (ini, autoload, database connection) 
* - creates user object (session)
* - sends the view to the router
* 
* How it works:
*   On PHP level: index.php + require/head.php -> classes/Router.php -> controller/BaseController.php -> controller/VIEWController.php 
*   On HTML level: template/VIEW.html -> inline javascript for given page and global CSS in css/main.css
*
*/
declare(strict_types=1);
require_once __DIR__ . '/require/head.php';

$user = new User();

$view = $_GET['view'] ?? 'main';

// if user not logged in the login page is the only place they can go
if(!$user -> getLoginStatus()) $view = 'login';
/*
 This is how you generate your first password. Copy the result next to your user name in the auth.yzhk file leike: "name:hash:"
 If you change the APPKEY you have to regenerate your password like this. Also in head.php you can change the file name. You should
 protect the file with .htaccess or other server methods. Same goes for the database file.
 $pw = new Password(APPKEY);
  echo '<pre>';
  var_dump($pw ->createPasswordHash('[your pw]'));
  die();
*/
$router = new Router($view);
$router->route(); 




