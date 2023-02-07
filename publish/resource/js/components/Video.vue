<template>
    <slot></slot>
</template>
<script>
import AgoraRTC from "agora-rtc-sdk-ng";
export default {
        name: 'Video',
        mounted() {
            //
            let options = {
                // Pass your App ID here.
                appId: "",
                // Set the channel name.
                channel: "",
                // Pass your temp token here.
                token: "",
                // Set the user ID.
                uid: 0,

                streamType: "video"
            };

            let channelParameters = {
                // A variable to hold a local audio track.
                localAudioTrack: null,
                // A variable to hold a local video track.
                localVideoTrack: null,
                // A variable to hold a remote audio track.
                remoteAudioTrack: null,
                // A variable to hold a remote video track.
                remoteVideoTrack: null,
                // A variable to hold the remote user id.s
                remoteUid: null,
            };

            async function startBasicCall() {

                console.log(options.streamType);

                // Create an instance of the Agora Engine
                document.getElementById('streaming').innerHTML = '<div id="loading">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" style="margin:auto;background:#fff;display:block;" width="200px" height="200px" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid">\n' +
                    '<g transform="translate(0 -7.5)">\n' +
                    '  <circle cx="50" cy="41" r="10" fill="#fe718d">\n' +
                    '    <animateTransform attributeName="transform" type="rotate" dur="1s" repeatCount="indefinite" keyTimes="0;1" values="0 50 50;360 50 50"></animateTransform>\n' +
                    '    <animate attributeName="r" dur="1s" repeatCount="indefinite" calcMode="spline" keyTimes="0;0.5;1" values="0;15;0" keySplines="0.2 0 0.8 1;0.2 0 0.8 1"></animate>\n' +
                    '  </circle>\n' +
                    '  <circle cx="50" cy="41" r="10" fill="#46dff0">\n' +
                    '    <animateTransform attributeName="transform" type="rotate" dur="1s" repeatCount="indefinite" keyTimes="0;1" values="180 50 50;540 50 50"></animateTransform>\n' +
                    '    <animate attributeName="r" dur="1s" repeatCount="indefinite" calcMode="spline" keyTimes="0;0.5;1" values="15;0;15" keySplines="0.2 0 0.8 1;0.2 0 0.8 1"></animate>\n' +
                    '  </circle>\n' +
                    '</g>\n' +
                    '</svg>' +
                    '</div>';

                options.appId = document.getElementById('appID').value;
                options.channel = document.getElementById('channelName').value;
                options.token = document.getElementById('agoraToken').value;
                options.uid = document.getElementById('uid').value;
                options.streamType = document.getElementById('streamType').value;

                console.log(options.token);
                console.log(options.channel);
                console.log(options.uid);


                const agoraEngine = AgoraRTC.createClient({ mode: "rtc", codec: "vp8" });
                // Dynamically create a container in the form of a DIV element to play the remote video track.
                const remotePlayerContainer = document.createElement("div");
                // Dynamically create a container in the form of a DIV element to play the local video track.
                const localPlayerContainer = document.createElement("div");
                // Specify the ID of the DIV container. You can use the uid of the local user.
                localPlayerContainer.id = options.uid;
                // Set the textContent property of the local video container to the local user id.
                // Set the local video container size.
                localPlayerContainer.style.width = "240px";
                localPlayerContainer.style.height = "180px";
                localPlayerContainer.style.padding = "15px 5px 5px 5px";
                // Set the remote video container size.
                remotePlayerContainer.style.width = "640px";
                remotePlayerContainer.style.height = "480px";
                remotePlayerContainer.style.padding = "15px 5px 5px 5px";


                // Listen for the "user-published" event to retrieve a AgoraRTCRemoteUser object.
                agoraEngine.on("user-published", async (user, mediaType) => {
                    // Subscribe to the remote user when the SDK triggers the "user-published" event.
                    await agoraEngine.subscribe(user, mediaType);
                    // Subscribe and play the remote video in the container If the remote user publishes a video track.
                    if (mediaType == "video") {
                        document.getElementById("avatar").remove();
                        console.log('start video client')
                        // Retrieve the remote video track.
                        channelParameters.remoteVideoTrack = user.videoTrack;
                        // Retrieve the remote audio track.
                        channelParameters.remoteAudioTrack = user.audioTrack;
                        // Save the remote user id for reuse.
                        channelParameters.remoteUid = user.uid.toString();
                        // Specify the ID of the DIV container. You can use the uid of the remote user.
                        remotePlayerContainer.id = user.uid.toString();
                        channelParameters.remoteUid = user.uid.toString();
                        // Append the remote container to the page body.
                        document.getElementById('streaming').append(remotePlayerContainer);
                        // Play the remote video track.
                        channelParameters.remoteVideoTrack.play(remotePlayerContainer);
                    }
                    // Subscribe and play the remote audio track If the remote user publishes the audio track only.
                    if (mediaType == "audio") {
                        console.log('start audio client')
                        // Get the RemoteAudioTrack object in the AgoraRTCRemoteUser object.
                        channelParameters.remoteAudioTrack = user.audioTrack;
                        // Play the remote audio track. No need to pass any DOM element.
                        channelParameters.remoteAudioTrack.play();
                    }
                    // Listen for the "user-unpublished" event.
                    agoraEngine.on("user-unpublished", (user) => {
                        console.log(user.uid + "has left the channel");
                    });
                });
                // Join a channel.
                await agoraEngine.join(
                    options.appId,
                    options.channel,
                    options.token,
                    options.uid
                );
                // Create a local audio track from the audio sampled by a microphone.
                if(options.streamType === 'video'){
                    channelParameters.localAudioTrack =
                        await AgoraRTC.createMicrophoneAudioTrack();
                    // Create a local video track from the video captured by a camera.
                    channelParameters.localVideoTrack =
                        await AgoraRTC.createCameraVideoTrack();
                }
                else if(options.streamType === 'audio'){
                    channelParameters.localAudioTrack =
                        await AgoraRTC.createMicrophoneAudioTrack();
                }

                // Append the local video container to the page body.
                document.getElementById('streaming').append(localPlayerContainer);
                // Publish the local audio and video tracks in the channel.
                if(options.streamType === 'video'){
                    await agoraEngine.publish([
                        channelParameters.localAudioTrack,
                        channelParameters.localVideoTrack,
                    ]);
                    channelParameters.localVideoTrack.play(localPlayerContainer);

                }
                else if(options.streamType === 'audio'){
                    await agoraEngine.publish([
                        channelParameters.localAudioTrack
                    ]);
                    channelParameters.localAudioTrack.play(localPlayerContainer);

                }

                // Play the local video track.
                console.log("publish success!");
                document.getElementById("loading").remove();


                // Listen to the Leave button click event.
                document.getElementById("leave").onclick = async function () {
                    // Destroy the local audio and video tracks.
                    if(options.streamType === 'video'){
                        channelParameters.localVideoTrack.close();
                        channelParameters.localAudioTrack.close();
                    }
                    else if(options.streamType === 'audio') {
                        channelParameters.localAudioTrack.close();
                    }

                    // Remove the containers you created for the local video and remote video.
                    removeVideoDiv(remotePlayerContainer.id);
                    removeVideoDiv(localPlayerContainer.id);
                    // Leave the channel
                    await agoraEngine.leave();
                    console.log("You left the channel");
                };
            }
            startBasicCall();

// Remove the video stream from the container.
            function removeVideoDiv(elementId) {
                console.log("Removing " + elementId + "Div");
                let Div = document.getElementById(elementId);
                if (Div) {
                    Div.remove();
                }
            }
        }
    };
</script>
