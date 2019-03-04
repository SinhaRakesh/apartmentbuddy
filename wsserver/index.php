<?php

require_once '../../../../../wsserver/vendor/autoload.php';

include '../config-default.php';
	
	$clients=[];

	//ssl websocket notification
	if(isset($config['ap-ssl-websocket-notifications']) && $config['ap-ssl-websocket-notifications']){
		$Context = \Hoa\Stream\Context::getInstance('APAPP');
		$Context->setOptions([
		    'ssl' => [
		        'local_cert' => $config['ap-ssl-certificate-pem-path'],
		        'verify_peer'=>false,
		        'allow_self_signed'=>true,
			'cafile'=>'/xavoc.com-ssl-cert/xavoc.com/ca.cer'
		    ]
		]);
		$ssl_config='APAPP';
	}else{
		$ssl_config=null;
	}
	$ssl_config=null;

	echo "eApartment WS Server Running at ".$config['ap-websocket-server']."\n";
	echo "----------------------------------------------------------------------\n";
	echo 'PHP Vesion: '.phpversion()."\n";
	echo "----------------------------------------------------------------------\n";

	$websocket = new Hoa\Websocket\Server(
	    new Hoa\Socket\Server($config['ap-websocket-server'],30,-1,$ssl_config)
	);

	$websocket->on('open', function (Hoa\Event\Bucket $bucket){
	    echo 'ws server new connection '."\n";
	    // todo check user is authorised or not
	    return;
	});

	/* 
		message = [
				'cmd'=>'' //register,notification,typing, stop typing, user left,
				'message'=>'msg string',
				'file_url'=>,
				'video_link'=>,
				'profile_image'=>
				'name'=>
				'clinets'=>[]
			]
	*/
	$websocket->on('message', function (Hoa\Event\Bucket $bucket) use(&$clients) {
		echo "wsp on message"."\n";

	    $data = $bucket->getData();
	    $message = json_decode($data['message'],true);
	    $response = "";
	    // echo json_encode($data);

	    if(isset($message['cmd'])){
	    	switch ($message['cmd']) {
	    		case 'register':
	    			$clients[$message['uu_id']] = $bucket->getSource()->getConnection()->getCurrentNode();
	    			$response = "Client ".(isset($message['name'])?$message['name']:"")." ".$message['uu_id']." registred \n";
					$response = ['cmd'=>'registered','message'=>$response,'register_uu_id'=>$message['uu_id']];
					$response = json_encode($response);
					// $bucket->getSource()->broadcast($response);
	    			// echo "wsp user registered \n";
	    			break;
	    		case 'typing':
	    			echo "server typing event \n";
	    			break;

	    		case 'stop typing':
	    			echo "server stop typing event \n";
	    			break;

	    		case 'chatmessage':
	    		case "notification":
	    			echo "wsp inside chat message \n";
	    			$response=[];
	    			foreach ($message['clients'] as $client) {
	    				if(isset($clients[$client])){
	    					// echo "wsp notification send \n";
		    				$bucket->getSource()->send(json_encode($message['message']),$clients[$client]);
		    				$response[]=$client; 
	    				}
	    			} 
	    			$response = json_encode($response);
	    			break;
	    	}
	    }

	    // echo "wsp bucket send "."\n";
	    $bucket->getSource()->send($response);
	    $config['wsclients'] = $clients;
	    return;
	});

	$websocket->on('close', function (Hoa\Event\Bucket $bucket) {
	    echo 'wsp websocket connection closed', "\n";
	    return;
	});

	// $websocket->on('ping',function(Hoa\Event\Bucket $bucket) {
	// 	echo 'wsp websocket ping received', "\n";
	//     return;
	// });

	$websocket->run();
