smasher
=======

A simple PHP load testing tool. Great if you've got some libraries in PHP you would like to flex a little bit.

This is something I put together while developing some PHP libraries, when trying to test performance of the libraries and the backend systems which those libraries interact with. Ideally the code would just include a class or common interface for testing, but as it stands it requires entering the PHP code you want to be part of the test block. 

There's a lot of things that need done to make this better! However, this script was enough to saturate all of the backend systems it touched. I tried to do similar testing with Jmeter, and some of the other test tools for this purpose, but I wasn't able to get good instrumentation on some PHP classes I was optimizing.
