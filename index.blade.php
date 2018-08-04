<!DOCTYPE html>
<html>
    <head>
        <title>My Video Chat</title>
        <link rel="stylesheet" type="text/css" href="//cloud.typography.com/7773252/764742/css/fonts.css" />
        <link rel="stylesheet" href="{{asset('css/style.min.css')}}">
        <link rel="icon" type="image/png" href="img/favicon.png">
        <style>
            .videoContainer {
                position: relative;
                width: 200px;
                height: 150px;
            }
            .videoContainer video {
                position: absolute;
                width: 100%;
                height: 100%;
            }
            .volume {
                position: absolute;
                left: 15%;
                width: 70%;
                bottom: 5px;
                height: 5px;
                display: none;
            }
            .connectionstate {
                position: absolute;
                top: 0px;
                width: 100%;
                text-align: center;
                color: #fff
            }
            #localScreenContainer {
                display: none;
            }
        </style>
    </head>
    <body>
        <h1 class="logo"><span>My</span><span>Chat</span></h1>

        <h3 id="title">Start a room</h3>
        <form id="createRoom">
            <input id="sessionInput"/>
            <button disabled type="submit">Create it!</button>
        </form>
        <p id="subTitle"></p>
        <div>
          <button id="screenShareButton"></button>
          (https required for screensharing to work)
        </div>
        <hr>
        <div class="videoContainer">
            <video id="localVideo" style="height: 150px;" oncontextmenu="return false;"></video>
            <meter id="localVolume" class="volume" min="-45" max="-20" high="-25" low="-40"></meter>
        </div>
        <div id="localScreenContainer" class="videoContainer">
        </div>
        <div id="remotes"></div>
        <hr>
        <!-- <div>
          <h3>A note on connectivity issues</h3>
          If you see black videos from peers or the connection state displayed ontop of those videos
          either hangs in <i>Connecting to peer...</i> or changes to <i>Connection failed</i>, a direct peer-to-peer connection could not be established.
          <br>
          This typically means that a TURN server is required. This <a href="http://www.html5rocks.com/en/tutorials/webrtc/infrastructure/#after-signaling-using-ice-to-cope-with-nats-and-firewalls">tutorial</a> explains the problem.
          You can check if those peers can connect each other on <a href="https://talky.io">talky.io</a> which runs a TURN server.
        </div>
        <div>
          Want to know more about how some of these features work? Check the <a href="notsosimple.html">documentation</a>
        </div> -->
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
        <script src="https://webrtc.github.io/adapter/adapter-latest.js"></script>
        <script src="{{asset('js/simplewebrtc.bundle.js')}}"></script>
        <script>
            // grab the room from the URL
            var room = location.search && location.search.split('?')[1];

            // create our webrtc connection
            var webrtc = new SimpleWebRTC({
                // the id/element dom element that will hold "our" video
                localVideoEl: 'localVideo',
                // the id/element dom element that will hold remote videos
                remoteVideosEl: '',
                // immediately ask for camera access
                autoRequestMedia: true,
                debug: false,
                detectSpeakingEvents: true,
                autoAdjustMic: false
            });

            // when it's ready, join if we got a room from the URL
            webrtc.on('readyToCall', function () {
                // you can name it anything
                if (room) webrtc.joinRoom(room);
            });

            function showVolume(el, volume) {
                if (!el) return;
                if (volume < -45) volume = -45; // -45 to -20 is
                if (volume > -20) volume = -20; // a good range
                el.value = volume;
            }

            // we got access to the camera
            webrtc.on('localStream', function (stream) {
                var button = document.querySelector('form>button');
                if (button) button.removeAttribute('disabled');
                $('#localVolume').show();
            });
            // we did not get access to the camera
            webrtc.on('localMediaError', function (err) {
            });

            // local screen obtained
            webrtc.on('localScreenAdded', function (video) {
                video.onclick = function () {
                    video.style.width = video.videoWidth + 'px';
                    video.style.height = video.videoHeight + 'px';
                };
                document.getElementById('localScreenContainer').appendChild(video);
                $('#localScreenContainer').show();
            });
            // local screen removed
            webrtc.on('localScreenRemoved', function (video) {
                document.getElementById('localScreenContainer').removeChild(video);
                $('#localScreenContainer').hide();
            });

            // a peer video has been added
            webrtc.on('videoAdded', function (video, peer) {
                console.log('video added', peer);
                var remotes = document.getElementById('remotes');
                if (remotes) {
                    var container = document.createElement('div');
                    container.className = 'videoContainer';
                    container.id = 'container_' + webrtc.getDomId(peer);
                    container.appendChild(video);

                    // suppress contextmenu
                    video.oncontextmenu = function () { return false; };

                    // resize the video on click
                    video.onclick = function () {
                        container.style.width = video.videoWidth + 'px';
                        container.style.height = video.videoHeight + 'px';
                    };

                    // show the remote volume
                    var vol = document.createElement('meter');
                    vol.id = 'volume_' + peer.id;
                    vol.className = 'volume';
                    vol.min = -45;
                    vol.max = -20;
                    vol.low = -40;
                    vol.high = -25;
                    container.appendChild(vol);

                    // show the ice connection state
                    if (peer && peer.pc) {
                        var connstate = document.createElement('div');
                        connstate.className = 'connectionstate';
                        container.appendChild(connstate);
                        peer.pc.on('iceConnectionStateChange', function (event) {
                            switch (peer.pc.iceConnectionState) {
                            case 'checking':
                                connstate.innerText = 'Connecting to peer...';
                                break;
                            case 'connected':
                            case 'completed': // on caller side
                                $(vol).show();
                                connstate.innerText = 'Connection established.';
                                break;
                            case 'disconnected':
                                connstate.innerText = 'Disconnected.';
                                break;
                            case 'failed':
                                connstate.innerText = 'Connection failed.';
                                break;
                            case 'closed':
                                connstate.innerText = 'Connection closed.';
                                break;
                            }
                        });
                    }
                    remotes.appendChild(container);
                }
            });
            // a peer was removed
            webrtc.on('videoRemoved', function (video, peer) {
                console.log('video removed ', peer);
                var remotes = document.getElementById('remotes');
                var el = document.getElementById(peer ? 'container_' + webrtc.getDomId(peer) : 'localScreenContainer');
                if (remotes && el) {
                    remotes.removeChild(el);
                }
            });

            // local volume has changed
            webrtc.on('volumeChange', function (volume, treshold) {
                showVolume(document.getElementById('localVolume'), volume);
            });
            // remote volume has changed
            webrtc.on('remoteVolumeChange', function (peer, volume) {
                showVolume(document.getElementById('volume_' + peer.id), volume);
            });

            // local p2p/ice failure
            webrtc.on('iceFailed', function (peer) {
                var connstate = document.querySelector('#container_' + webrtc.getDomId(peer) + ' .connectionstate');
                console.log('local fail', connstate);
                if (connstate) {
                    connstate.innerText = 'Connection failed.';
                    fileinput.disabled = 'disabled';
                }
            });

            // remote p2p/ice failure
            webrtc.on('connectivityError', function (peer) {
                var connstate = document.querySelector('#container_' + webrtc.getDomId(peer) + ' .connectionstate');
                console.log('remote fail', connstate);
                if (connstate) {
                    connstate.innerText = 'Connection failed.';
                    fileinput.disabled = 'disabled';
                }
            });

            // Since we use this twice we put it here
            function setRoom(name) {
                document.querySelector('form').remove();
                document.getElementById('title').innerText = 'Room: ' + name;
                document.getElementById('subTitle').innerText =  'Link to join: ' + location.href;
                $('body').addClass('active');
            }

            if (room) {
                setRoom(room);
            } else {
                $('form').submit(function () {
                    var val = $('#sessionInput').val().toLowerCase().replace(/\s/g, '-').replace(/[^A-Za-z0-9_\-]/g, '');
                    webrtc.createRoom(val, function (err, name) {
                        console.log(' create room cb', arguments);
                        console.log(arguments[1]);

                        var newUrl = location.pathname + '?' + name;
                        if (!err) {
                            history.replaceState({foo: 'bar'}, null, newUrl);
                            setRoom(name);
                        } else {
                            console.log(err);
                        }
                    });
                    return false;
                });
            }

            var button = document.getElementById('screenShareButton'),
                setButton = function (bool) {
                    button.innerText = bool ? 'share screen' : 'stop sharing';
                };
            if (!webrtc.capabilities.supportScreenSharing) {
                button.disabled = 'disabled';
            }
            webrtc.on('localScreenRemoved', function () {
                setButton(true);
            });

            setButton(true);

            button.onclick = function () {
                if (webrtc.getLocalScreen()) {
                    webrtc.stopScreenShare();
                    setButton(true);
                } else {
                    webrtc.shareScreen(function (err) {
                        if (err) {
                            setButton(true);
                        } else {
                            setButton(false);
                        }
                    });

                }
            };
        </script>
    </body>
</html>
