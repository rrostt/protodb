ProtoDB
=======

v0.1.1

Library for simple and quick use of databases when prototyping new applications and service.

Having to properly setup a database structure and write queries for creating and operating tables, can sometimes
get in the way of creativity. This tool attempts to get that out of the way by setting up the database structure
as you use it. When inserting something into a table for the first time, will create the table and add columns as
it appears that you want there to be tables. Example:

	DB::insert("books", array("title" => "Mobility is the Message", "author" => "Mattias Rost"));

The above statement will start by making sure there is a table with the name "books". If not, it will create one with
a BIGINT `id` as a primary key. It will then check if the columns "title" and "author" exists, and if not it will
add the columns to the database structure, before inserting a new row.
A simple query from the database is easily done through

	DB::get("books");

or

	DB::get("books", array("author" => "Mattias Rost"));

Furthermore, it includes a REST-ful API that allow your client app to directly store stuff in a database on your server.
There is also a javascript-library included, that let you do all your protodb'ing from within a browser.

This file includes
	1) a PHP library for database storage, 
	2) a REST-ful api for use remotely, and
	3) a javascript library for use together with jQuery.

Usage
-----

From PHP:

	create a db.php file that establishes a mysql database connection and selects database.
	include('protodb.php');
	Use the functions from DB:: which is a class with static functions.
	
From Javascript:

	<script src="protodb.php?_js"></script>
	<script>
		protodb.insert("books", {author:"Mattias Rost", title:"ProtoDB for dummies"});
		protodb.get("books", {author:"Mattias Rost"}, function(rows) {
			rows.forEach(function(row) {
				console.log(row);
			});
		});
	</script>
	
From other external client:

	To insert, HTTP POST with _protodb variable set, and "cmd=insert", "table=books", "values={\"author\":\"Mattias Rost\", \"title\":\"ProtoDB for dummies\"}"
	To get, HTTP GET with _protodb, and "cmd=get", and optional where, json-string.

Credits
=======
Mattias Rost
http://rost.me

