/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */
var app = {

	urlservidor: 'http://www.st0rage.org/~thoso/interativapush/',

    // Application Constructor
    initialize: function() {
        this.bindEvents();
    },
    // Bind Event Listeners
    //
    // Bind any events that are required on startup. Common events are:
    // 'load', 'deviceready', 'offline', and 'online'.
    bindEvents: function() {
        document.addEventListener('deviceready', this.onDeviceReady, false);
    },
    // deviceready Event Handler
    //
    // The scope of 'this' is the event. In order to call the 'receivedEvent'
    // function, we must explicity call 'app.receivedEvent(...);'
    onDeviceReady: function() {
        app.pushInit();
    },
    
	
	
    pushInit: function() {
    	$('#login').submit(function(event) {
    		app.enviaRegistro();
    		event.preventDefault();
    	});
    	$('#login .button').attr('disabled','disabled');

		$('<li></li>').html('Registrando '+device.platform).appendTo('#info-list');
		var pushNotification = window.plugins.pushNotification;
		if ( device.platform == 'android' || device.platform == 'Android'){
			pushNotification.register(
				app.successHandler,
				app.errorHandler,
				{
					'senderID':'895995005522',
					'ecb':'app.onNotificationGCM'
				}
			);
		}
		else {
			pushNotification.register(
				app.tokenHandler,
				app.errorHandler,
				{
					"badge":"true",
					"sound":"true",
					"alert":"true",
					"ecb":"app.onNotificationAPN"
				}
			);
		}
    },
		
	successHandler: function(result) {
		$('<li></li>').html('Conexão bem sucedida: '+result).appendTo('#info-list');
	},
	
	errorHandler:function(error) {
		$('<li></li>').html('Erro: ' + error).appendTo('#info-list');
	},
	
	enviaRegistro: function(data) {
		$('#login .button').attr('disabled','disabled');
		$('#registrando-notify').css('display','block');
		$.get(app.urlservidor+'index.php?action=assign&plataforma=' + device.platform + '&' + $('#login').serialize(),function(result) {
			console.log(result);
			var data = $.parseJSON(result);
			if (data.error == 1) {
				alert('Assign Incompleto, confira as informações e tente novamente.');
				$('<li></li>').html('Erro: Assign incompleto, informações insuficientes.').appendTo('#info-list');
				$('#login .button').removeAttr('disabled');
				$('#registrando-notify').css('display','none');
			}
			else if (data.success) {
				if (data.success == 1) {
					$('<li></li>').html('Registro adicionado!').appendTo('#info-list');
				}
				else {
					$('<li></li>').html('Registro atualizado!').appendTo('#info-list');
				}
				$('<li></li>').html('Pronto para receber notificações push individualmente.').appendTo('#info-list');
				$('#registrando-notify').css('display','none');
				$('#registrado-chk').css('display','block');
				$('#login').attr('disabled','disabled');
			}
			else {
				alert('Erro desconhecido. Tente novamente.');
				$('<li></li>').html('Erro desconhecido').appendTo('#info-list');
				$('#login .button').removeAttr('disabled');
				$('#registrando-notify').css('display','none');
			}
		});
	},

	tokenHandler: function(result) {
		$('#login .button').removeAttr('disabled');
		// enviar para o servidor o token do iOS para uso posterior
		$("#login input[name='registro']").value = result;
		$('<li></li>').html('Registration Token = '+result).appendTo('#info-list');
	},
	
	onNotificationGCM: function(e) {
        switch(e.event)
        {
            case 'registered':
                if (e.regid.length > 0)
                {
                	$('#login .button').removeAttr('disabled');
					// Enviar para o servidor o regID do android para uso posterior
					$("#login input[name='registro']").value = e.regid;
					$('<li></li>').html('Registration id = '+e.regid).appendTo('#info-list');
                }
            break;
 
            case 'message':
				if (e.foreground) {
					// o app estava aberto em primeiro plano
					$('<li></li>').html('Notificação com o app em primeiro plano').appendTo('#info-list');
					
					if (e.soundname) {
						$('<li></li>').html('Tocando som de notificação').appendTo('#info-list');
						var notif_media = new Media("/android_asset/www/"+ e.soundname);
						notif_media.play();
					}
				}
				else {
					// o app não estava em primeiro plano, o usuario abriu pela barra de notificações
					if (e.coldstart) {
						// o app estava fechado e foi aberto pela primeira vez
						$('<li></li>').html('Notificação com o app fechado e foi aberto pela primeira vez').appendTo('#info-list');
					}
					else {
						// o app estava aberto, mas em background
						$('<li></li>').html('O app estava aberto, mas em background').appendTo('#info-list');
					}
				}
			
				// this is the actual push notification. its format depends on the data model from the push server
				alert('Mensagem = '+e.message+' Msgcnt = '+e.msgcnt);
				$('<li></li>').html('Mensagem = '+e.message+' Msgcnt = '+e.msgcnt).appendTo('#info-list');
            break;
 
            case 'error':
				$('<li></li>').html('GCM error = '+e.msg).appendTo('#info-list');
            break;
 
            default:
				$('<li></li>').html('Um evento desconhecido do GCM ocorreu').appendTo('#info-list');
				break;
        }
    },
	
	onNotificationAPN: function(e) {
		if (e.alert) {
			alert('Mensagem = '+e.alert);
			$('<li></li>').html('Mensagem = '+e.alert).appendTo('#info-list');
		}

		if (e.sound) {
			$('<li></li>').html('Tocando som de notificação').appendTo('#info-list');
			var notif_media = new Media(e.sound);
			notif_media.play();
		}

		if (e.badge) {
			pushNotification.setApplicationIconBadgeNumber(successHandler, e.badge);
		}
	}
};



function getEx(url, callback) {
	id = Math.floor(Math.random() * 100000);
	$('<iframe></iframe>').css('display','none').attr('id','getEx_'+id).appendTo(document.body);
	$('#getEx_'+id).attr('src', url).ready(function(){
		data = document.getElementById('getEx_'+id);
		callback(data.innerHTML);
		//$('#getEx_'+id).remove();
	});
}
