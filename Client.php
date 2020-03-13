<?php
    $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
    $client->on("connect", function($cli) {
        $cli->send("hello world\n");
    });
    $client->on("receive", function($cli, $data){
        echo "received: {$data}\n";
    });
    $client->on("error", function($cli){
        echo "connect failed\n";
    });
    $client->on("close", function($cli){
        echo "connection close\n";
    });
    $client->connect("192.168.0.130", 8000);