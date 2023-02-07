<template>
    <slot></slot>
</template>

<script>
import Pusher from 'pusher-js';
import  NProgress from 'nprogress';
import autosize from 'autosize';
export default {
    data() {
        return {
            currentAudioName:"",
            isVisibleLink: false,
            streamingPresenceChannel: null,
            streamingUsers: [],
            currentlyContactedUser: null,
            allPeers: {}, // this will hold all dynamically created peers using the 'ID' of users who just joined as keys
        };
    },
    computed: {
        streamId() {
            // you can improve streamId generation code. As long as we include the
            // broadcaster's user id, we are assured of getting unique streamiing link everytime.
            // the current code just generates a fixed streaming link for a particular user.
            return `${this.auth_user_id}12acde2`;
        },
        streamLink() {
            // just a quick fix. can be improved by setting the app_url
            if (this.env === "production") {
                return `https://laravel-video-call.herokuapp.com/streaming/${this.streamId}`;
            } else {
                return `https://webrtc.test/admin/join?stream_id=${this.streamId}`;
            }
        },
    },
    methods: {
        async startMeeting() {
            const stream = await this.getPermissions();
            this.$refs.broadcaster.srcObject = stream;
            this.initializeStreamingChannel();
            this.initializeSignalAnswerChannel(); // a private channel where the broadcaster listens to incoming signalling answer
            this.isVisibleLink = true;
        },
        async getPermissions() {
            // Older browsers might not implement mediaDevices at all, so we set an empty object first
            if (navigator.mediaDevices === undefined) {
                navigator.mediaDevices = {};
            }

            // Some browsers partially implement media devices. We can't just assign an object
            // with getUserMedia as it would overwrite existing properties.
            // Here, we will just add the getUserMedia property if it's missing.
            if (navigator.mediaDevices.getUserMedia === undefined) {
                navigator.mediaDevices.getUserMedia = function (constraints) {
                    // First get ahold of the legacy getUserMedia, if present
                    const getUserMedia =
                        navigator.webkitGetUserMedia || navigator.mozGetUserMedia;

                    // Some browsers just don't implement it - return a rejected promise with an error
                    // to keep a consistent interface
                    if (!getUserMedia) {
                        return Promise.reject(
                            new Error("getUserMedia is not implemented in this browser")
                        );
                    }

                    // Otherwise, wrap the call to the old navigator.getUserMedia with a Promise
                    return new Promise((resolve, reject) => {
                        getUserMedia.call(navigator, constraints, resolve, reject);
                    });
                };
            }
            navigator.mediaDevices.getUserMedia =
                navigator.mediaDevices.getUserMedia ||
                navigator.webkitGetUserMedia ||
                navigator.mozGetUserMedia;

            return new Promise((resolve, reject) => {
                navigator.mediaDevices
                    .getUserMedia({video: true, audio: true})
                    .then(stream => {
                        resolve(stream);
                    })
                    .catch(err => {
                        reject(err);
                        //   throw new Error(`Unable to fetch stream ${err}`);
                    });
            });
        },
        peerCreator(stream, user, signalCallback) {
            let peer;
            return {
                create: () => {
                    peer = new Peer({
                        initiator: true,
                        trickle: false,
                        stream: stream,
                        config: {
                            iceServers: [
                                {
                                    urls: "stun:stun.stunprotocol.org",
                                },
                                {
                                    urls: this.turn_url,
                                    username: this.turn_username,
                                    credential: this.turn_credential,
                                },
                            ],
                        },
                    });
                },
                getPeer: () => peer,
                initEvents: () => {
                    console.log('start webRTC Events')
                    peer.on("signal", (data) => {
                        // send offer over here.
                        signalCallback(data, user);
                    });
                    peer.on("stream", (stream) => {
                        console.log("onStream");
                    });
                    peer.on("track", (track, stream) => {
                        console.log("onTrack");
                    });
                    peer.on("connect", () => {
                        console.log("Broadcaster Peer connected");
                    });
                    peer.on("close", () => {
                        console.log("Broadcaster Peer closed");
                    });
                    peer.on("error", (err) => {
                        console.log("handle error gracefully");
                    });
                },
            };
        },
        initializeStreamingChannel() {
            console.log('Start Channel');
            this.streamingPresenceChannel = window.Echo.join(
                `streaming-channel.${this.streamId}`
            );
            this.streamingPresenceChannel.here((users) => {
                this.streamingUsers = users;
            });
            this.streamingPresenceChannel.joining((user) => {
                console.log("New User", user);
                // if this new user is not already on the call, send your stream offer
                const joiningUserIndex = this.streamingUsers.findIndex(
                    (data) => data.id === user.id
                );
                if (joiningUserIndex < 0) {
                    console.log('Create Stream User');

                    this.streamingUsers.push(user);
                    // A new user just joined the channel so signal that user
                    this.currentlyContactedUser = user.id;
                    this.allPeers[user.id] = this.peerCreator(
                        this.$refs.broadcaster.srcObject,
                        user,
                        this.signalCallback
                    )
                    // Create Peer
                    this.allPeers[user.id].create();
                    // Initialize Events
                    this.allPeers[user.id].initEvents();

                    console.log(this.allPeers[user.id]);
                }
            });
            this.streamingPresenceChannel.leaving((user) => {
                console.log(user.name, "Left");
                if (this.allPeers[user.id]) {
                    // destroy peer
                    this.allPeers[user.id].getPeer().destroy();
                    // delete peer object
                    delete this.allPeers[user.id];
                    // if one leaving is the broadcaster set streamingUsers to empty array
                    if (user.id === this.auth_user_id) {
                        this.streamingUsers = [];
                    } else {
                        // remove from streamingUsers array
                        const leavingUserIndex = this.streamingUsers.findIndex(
                            (data) => data.id === user.id
                        );
                        this.streamingUsers.splice(leavingUserIndex, 1);
                    }
                }

            });
        },
        initializeSignalAnswerChannel() {
            window.Echo.private(`stream-signal-channel.${this.auth_user_id}`).listen(
                ".answer",
                (data) => {
                    console.log("Signal Answer from private channel");
                    if (data.data.answer.renegotiate) {
                        console.log("renegotating");
                    }
                    if (data.data.answer.sdp) {
                        const updatedSignal = {
                            ...data.data.answer,
                            sdp: `${data.data.answer.sdp}\n`,
                        };
                        this.allPeers[this.currentlyContactedUser]
                            .getPeer()
                            .signal(updatedSignal);
                    }
                }
            );
        },
        signalCallback(offer, user) {
            axios
                .post(this.route('stream.offer'), {
                    broadcaster: this.auth_user_id,
                    receiver: user,
                    offer,
                })
                .then((res) => {
                    console.log(res);
                })
                .catch((err) => {
                    console.log(err);
                });
        },
    },
    mounted() {
        /**
         *-------------------------------------------------------------
         * Global variables
         *-------------------------------------------------------------
         */

        Pusher.logToConsole = true;

        var pusher = new Pusher(import.meta.env.VITE_PUSHER_APP_KEY, {
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
            key: import.meta.env.VITE_PUSHER_APP_KEY,
            wsHost: import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
            wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
            wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: $('meta[name="pusher-auth"]').attr('content'),
            auth: {
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            }
        });

        const allowedImages = ['image/jpeg', 'image/png', 'image/gif']
        const allowedFiles = ['jpeg', 'png', 'gif'];
        const getAllowedExtensions = [...allowedImages, ...allowedFiles];
        const getMaxUploadSize = '10mb';

        var messenger,
            typingTimeout,
            typingNow = 0,
            temporaryMsgId = 0,
            defaultAvatarInSettings = null,
            messengerColor,
            dark_mode,
            messages_page = 1;

        const messagesContainer = $(".messenger-messagingView .m-body"),
            messengerTitleDefault = $(".messenger-headTitle").text(),
            messageInput = $("#message-form .m-send"),
            auth_id = $("meta[name=auth]").attr("content"),
            url = $("meta[name=url]").attr("content"),
            defaultMessengerColor = $("meta[name=messenger-color]").attr("content"),
            access_token = $('meta[name="csrf-token"]').attr("content");

        const getMessengerId = () => $("meta[name=id]").attr("content");
        const getMessengerType = () => $("meta[name=type]").attr("content");
        const setMessengerId = (id) => $("meta[name=id]").attr("content", id);
        const setMessengerType = (type) => $("meta[name=type]").attr("content", type);

        $('#startVideo').on('click', function(){
            if (confirm("Start a Video Call?")){
                let videoURL = $("meta[name=base]").attr("content") + '/profile/video/' + $("meta[name=id]").attr("content") + '/video';
                $.ajax({
                    url: videoURL,
                    method: "GET",
                    dataType: "JSON",
                    processData: false,
                    contentType: false,
                });
            }
        });

        $('#startAudio').on('click', function(){
            if (confirm("Start a Audio Call?")){
                let audioURL = $("meta[name=base]").attr("content") + '/profile/video/' + $("meta[name=id]").attr("content") + '/audio';
                $.ajax({
                    url: audioURL,
                    method: "GET",
                    dataType: "JSON",
                    processData: false,
                    contentType: false,
                });
            }
        });


        /**
         *-------------------------------------------------------------
         * Re-usable methods
         *-------------------------------------------------------------
         */
        const escapeHtml = (unsafe) => {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;");
        };
        function actionOnScroll(selector, callback, topScroll = false) {
            $(selector).on("scroll", function () {
                let element = $(this).get(0);
                const condition = topScroll
                    ? element.scrollTop == 0
                    : element.scrollTop + element.clientHeight >= element.scrollHeight;
                if (condition) {
                    callback();
                }
            });
        }
        function routerPush(title, url) {
            return window.history.pushState({}, title || document.title, url);
        }
        function updateSelectedContact(user_id) {
            $(document).find(".messenger-list-item").removeClass("m-list-active");
            $(document)
                .find(
                    ".messenger-list-item[data-contact=" + (user_id || getMessengerId()) + "]"
                )
                .addClass("m-list-active");
        }
        /**
         *-------------------------------------------------------------
         * Global Templates
         *-------------------------------------------------------------
         */
// Loading svg
        function loadingSVG(size = "25px", className = "", style = "") {
            return `
<svg style="${style}" class="loadingSVG ${className}" xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 40 40" stroke="#ffffff">
<g fill="none" fill-rule="evenodd">
<g transform="translate(2 2)" stroke-width="3">
<circle stroke-opacity=".1" cx="18" cy="18" r="18"></circle>
<path d="M36 18c0-9.94-8.06-18-18-18" transform="rotate(349.311 18 18)">
<animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur=".8s" repeatCount="indefinite"></animateTransform>
</path>
</g>
</g>
</svg>
`;
        }
        function loadingWithContainer(className) {
            return `<div class="${className}" style="text-align:center;padding:15px">${loadingSVG(
                "25px",
                "",
                "margin:auto"
            )}</div>`;
        }

// loading placeholder for users list item
        function listItemLoading(items) {
            let template = "";
            for (let i = 0; i < items; i++) {
                template += `
<div class="loadingPlaceholder">
<div class="loadingPlaceholder-wrapper">
<div class="loadingPlaceholder-body">
<table class="loadingPlaceholder-header">
<tr>
<td style="width: 45px;"><div class="loadingPlaceholder-avatar"></div></td>
<td>
  <div class="loadingPlaceholder-name"></div>
      <div class="loadingPlaceholder-date"></div>
</td>
</tr>
</table>
</div>
</div>
</div>
`;
            }
            return template;
        }

// loading placeholder for avatars
        function avatarLoading(items) {
            let template = "";
            for (let i = 0; i < items; i++) {
                template += `
<div class="loadingPlaceholder">
<div class="loadingPlaceholder-wrapper">
<div class="loadingPlaceholder-body">
<table class="loadingPlaceholder-header">
    <tr>
        <td style="width: 45px;">
            <div class="loadingPlaceholder-avatar" style="margin: 2px;"></div>
        </td>
    </tr>
</table>
</div>
</div>
</div>
`;
            }
            return template;
        }

// While sending a message, show this temporary message card.
        function sendTempMessageCard(message, id) {
            return `
<div class="message-card mc-sender" data-id="${id}">
  <p>
      ${message}
      <sub>
          <span class="far fa-clock"></span>
      </sub>
  </p>
</div>
`;
        }
// upload image preview card.
        function attachmentTemplate(fileType, fileName, imgURL = null) {
            if (fileType != "image") {
                return (
                    `
<div class="attachment-preview">
<span class="fas fa-times cancel"></span>
<p style="padding:0px 30px;"><span class="fas fa-file"></span> ` +
                    escapeHtml(fileName) +
                    `</p>
</div>
`
                );
            } else {
                return (
                    `
<div class="attachment-preview">
<span class="fas fa-times cancel"></span>
<div class="image-file chat-image" style="background-image: url('` +
                    imgURL +
                    `');"></div>
<p><span class="fas fa-file-image"></span> ` +
                    escapeHtml(fileName) +
                    `</p>
</div>
`
                );
            }
        }

// Active Status Circle
        function activeStatusCircle() {
            return `<span class="activeStatus"></span>`;
        }

        /**
         *-------------------------------------------------------------
         * Css Media Queries [For responsive design]
         *-------------------------------------------------------------
         */
        $(window).resize(function () {
            cssMediaQueries();
        });
        function cssMediaQueries() {
            if (window.matchMedia("(min-width: 980px)").matches) {
                $(".messenger-listView").removeAttr("style");
            }
            if (window.matchMedia("(max-width: 980px)").matches) {
                $("body")
                    .find(".messenger-list-item")
                    .find("tr[data-action]")
                    .attr("data-action", "1");
                $("body").find(".favorite-list-item").find("div").attr("data-action", "1");
            } else {
                $("body")
                    .find(".messenger-list-item")
                    .find("tr[data-action]")
                    .attr("data-action", "0");
                $("body").find(".favorite-list-item").find("div").attr("data-action", "0");
            }
        }

        /**
         *-------------------------------------------------------------
         * App Modal
         *-------------------------------------------------------------
         */
        let app_modal = function ({
                                      show = true,
                                      name,
                                      data = 0,
                                      buttons = true,
                                      header = null,
                                      body = null,
                                  }) {
            const modal = $(".app-modal[data-name=" + name + "]");
            // header
            header ? modal.find(".app-modal-header").html(header) : "";

            // body
            body ? modal.find(".app-modal-body").html(body) : "";

            // buttons
            buttons == true
                ? modal.find(".app-modal-footer").show()
                : modal.find(".app-modal-footer").hide();

            // show / hide
            if (show == true) {
                modal.show();
                $(".app-modal-card[data-name=" + name + "]").addClass("app-show-modal");
                $(".app-modal-card[data-name=" + name + "]").attr("data-modal", data);
            } else {
                modal.hide();
                $(".app-modal-card[data-name=" + name + "]").removeClass("app-show-modal");
                $(".app-modal-card[data-name=" + name + "]").attr("data-modal", data);
            }
        };

        /**
         *-------------------------------------------------------------
         * Slide to bottom on [action] - e.g. [message received, sent, loaded]
         *-------------------------------------------------------------
         */
        function scrollToBottom(container) {
            $(container)
                .stop()
                .animate({
                    scrollTop: $(container)[0].scrollHeight,
                });
        }

        /**
         *-------------------------------------------------------------
         * click and drag to scroll - function
         *-------------------------------------------------------------
         */
        function hScroller(scroller) {
            const slider = document.querySelector(scroller);
            let isDown = false;
            let startX;
            let scrollLeft;

            slider.addEventListener("mousedown", (e) => {
                isDown = true;
                startX = e.pageX - slider.offsetLeft;
                scrollLeft = slider.scrollLeft;
            });
            slider.addEventListener("mouseleave", () => {
                isDown = false;
            });
            slider.addEventListener("mouseup", () => {
                isDown = false;
            });
            slider.addEventListener("mousemove", (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - slider.offsetLeft;
                const walk = (x - startX) * 1;
                slider.scrollLeft = scrollLeft - walk;
            });
        }

        /**
         *-------------------------------------------------------------
         * Disable/enable message form fields, messaging container...
         * on load info or if needed elsewhere.
         *
         * Default : true
         *-------------------------------------------------------------
         */
        function disableOnLoad(action = true) {
            if (action == true) {
                // hide star button
                $(".add-to-favorite").hide();
                // hide send card
                $(".messenger-sendCard").hide();
                // add loading opacity to messages container
                messagesContainer.css("opacity", ".5");
                // disable message form fields
                messageInput.attr("readonly", "readonly");
                $("#message-form button").attr("disabled", "disabled");
                $(".upload-attachment").attr("disabled", "disabled");
            } else {
                // show star button
                if (getMessengerId() != auth_id) {
                    $(".add-to-favorite").show();
                }
                // show send card
                $(".messenger-sendCard").show();
                // remove loading opacity to messages container
                messagesContainer.css("opacity", "1");
                // enable message form fields
                messageInput.removeAttr("readonly");
                $("#message-form button").removeAttr("disabled");
                $(".upload-attachment").removeAttr("disabled");
            }
        }

        /**
         *-------------------------------------------------------------
         * Error message card
         *-------------------------------------------------------------
         */
        function errorMessageCard(id) {
            messagesContainer
                .find(".message-card[data-id=" + id + "]")
                .addClass("mc-error");
            messagesContainer
                .find(".message-card[data-id=" + id + "]")
                .find("svg.loadingSVG")
                .remove();
            messagesContainer
                .find(".message-card[data-id=" + id + "] p")
                .prepend('<span class="fas fa-exclamation-triangle"></span>');
        }

        /**
         *-------------------------------------------------------------
         * Fetch id data (user/group) and update the view
         *-------------------------------------------------------------
         */
        function IDinfo(id, type) {
            // clear temporary message id
            temporaryMsgId = 0;
            // clear typing now
            typingNow = 0;
            // show loading bar
            NProgress.start();
            // disable message form
            disableOnLoad();
            if (messenger != 0) {
                // get shared photos
                getSharedPhotos(id);
                // Get info
                $.ajax({
                    url: url + "/idInfo",
                    method: "POST",
                    data: { _token: access_token, id, type },
                    dataType: "JSON",
                    success: (data) => {
                        // avatar photo
                        $(".messenger-infoView")
                            .find(".avatar")
                            .css("background-image", 'url("' + data.user_avatar + '")');
                        $(".header-avatar").css(
                            "background-image",
                            'url("' + data.user_avatar + '")'
                        );
                        // Show shared and actions
                        $(".messenger-infoView-btns .delete-conversation").show();
                        $(".messenger-infoView-shared").show();
                        // fetch messages
                        fetchMessages(id, type, true);
                        // focus on messaging input
                        messageInput.focus();
                        // update info in view
                        $(".messenger-infoView .info-name").html(data.fetch.name);
                        $(".m-header-messaging .user-name").html(data.fetch.name);
                        // Star status
                        data.favorite > 0
                            ? $(".add-to-favorite").addClass("favorite")
                            : $(".add-to-favorite").removeClass("favorite");
                        // form reset and focus
                        $("#message-form").trigger("reset");
                        cancelAttachment();
                        messageInput.focus();
                    },
                    error: () => {
                        console.error("Error, check server response!");
                        // remove loading bar
                        NProgress.done();
                        NProgress.remove();
                    },
                });
            } else {
                // remove loading bar
                NProgress.done();
                NProgress.remove();
            }
        }

        /**
         *-------------------------------------------------------------
         * Send message function
         *-------------------------------------------------------------
         */
        function sendMessage() {
            temporaryMsgId += 1;
            let tempID = `temp_${temporaryMsgId}`;
            let hasFile = !!$(".upload-attachment").val();
            const inputValue = $.trim(messageInput.val());
            if (inputValue.length > 0 || hasFile) {
                const formData = new FormData($("#message-form")[0]);
                formData.append("id", getMessengerId());
                formData.append("type", getMessengerType());
                formData.append("temporaryMsgId", tempID);
                formData.append("_token", access_token);
                $.ajax({
                    url: $("#message-form").attr("action"),
                    method: "POST",
                    data: formData,
                    dataType: "JSON",
                    processData: false,
                    contentType: false,
                    beforeSend: () => {
                        // remove message hint
                        $(".messages").find(".message-hint").remove();
                        // append a temporary message card
                        if (hasFile) {
                            messagesContainer
                                .find(".messages")
                                .append(
                                    sendTempMessageCard(
                                        inputValue + "\n" + loadingSVG("28px"),
                                        tempID
                                    )
                                );
                        } else {
                            messagesContainer
                                .find(".messages")
                                .append(sendTempMessageCard(inputValue, tempID));
                        }
                        // scroll to bottom
                        scrollToBottom(messagesContainer);
                        messageInput.css({ height: "42px" });
                        // form reset and focus
                        $("#message-form").trigger("reset");
                        cancelAttachment();
                        messageInput.focus();
                    },
                    success: (data) => {
                        if (data.error > 0) {
                            // message card error status
                            errorMessageCard(tempID);
                            console.error(data.error_msg);
                        } else {
                            // update contact item
                            updateContactItem(getMessengerId());
                            // temporary message card
                            const tempMsgCardElement = messagesContainer.find(
                                `.message-card[data-id=${data.tempID}]`
                            );
                            // add the message card coming from the server before the temp-card
                            tempMsgCardElement.before(data.message);
                            // then, remove the temporary message card
                            tempMsgCardElement.remove();
                            // scroll to bottom
                            scrollToBottom(messagesContainer);
                            // send contact item updates
                            sendContactItemUpdates(true);
                        }
                    },
                    error: () => {
                        // message card error status
                        errorMessageCard(tempID);
                        // error log
                        console.error(
                            "Failed sending the message! Please, check your server response."
                        );
                    },
                });
            }
            return false;
        }

        /**
         *-------------------------------------------------------------
         * Fetch messages from database
         *-------------------------------------------------------------
         */
        let messagesPage = 1;
        let noMoreMessages = false;
        let messagesLoading = false;
        function setMessagesLoading(loading = false) {
            if (!loading) {
                messagesContainer.find(".messages").find(".loading-messages").remove();
                NProgress.done();
                NProgress.remove();
            } else {
                messagesContainer
                    .find(".messages")
                    .prepend(loadingWithContainer("loading-messages"));
            }
            messagesLoading = loading;
        }
        function fetchMessages(id, type, newFetch = false) {
            if (newFetch) {
                messagesPage = 1;
                noMoreMessages = false;
            }
            if (messenger != 0 && !noMoreMessages && !messagesLoading) {
                const messagesElement = messagesContainer.find(".messages");
                setMessagesLoading(true);
                $.ajax({
                    url: url + "/fetchMessages",
                    method: "POST",
                    data: {
                        _token: access_token,
                        id: id,
                        type: type,
                        page: messagesPage,
                    },
                    dataType: "JSON",
                    success: (data) => {
                        setMessagesLoading(false);
                        if (messagesPage == 1) {
                            messagesElement.html(data.messages);
                            scrollToBottom(messagesContainer);
                        } else {
                            const lastMsg = messagesElement.find(
                                messagesElement.find(".message-card")[0]
                            );
                            const curOffset =
                                lastMsg.offset().top - messagesContainer.scrollTop();
                            messagesElement.prepend(data.messages);
                            messagesContainer.scrollTop(lastMsg.offset().top - curOffset);
                        }
                        // trigger seen event
                        makeSeen(true);
                        // Pagination lock & messages page
                        noMoreMessages = messagesPage >= data?.last_page;
                        if (!noMoreMessages) messagesPage += 1;
                        // Enable message form if messenger not = 0; means if data is valid
                        if (messenger != 0) {
                            disableOnLoad(false);
                        }
                    },
                    error: (error) => {
                        setMessagesLoading(false);
                        console.error(error);
                    },
                });
            }
        }

        /**
         *-------------------------------------------------------------
         * Cancel file attached in the message.
         *-------------------------------------------------------------
         */
        function cancelAttachment() {
            $(".messenger-sendCard").find(".attachment-preview").remove();
            $(".upload-attachment").replaceWith(
                $(".upload-attachment").val("").clone(true)
            );
        }

        /**
         *-------------------------------------------------------------
         * Cancel updating avatar in settings
         *-------------------------------------------------------------
         */
        function cancelUpdatingAvatar() {
            $(".upload-avatar-preview").css("background-image", defaultAvatarInSettings);
            $(".upload-avatar").replaceWith($(".upload-avatar").val("").clone(true));
        }

        /**
         *-------------------------------------------------------------
         * Pusher channels and event listening..
         *-------------------------------------------------------------
         */

// subscribe to the channel
        const channelName = "private-chatify";
        var channel = pusher.subscribe(`${channelName}.${auth_id}`);
        var clientSendChannel;
        var clientListenChannel;

        function initClientChannel() {
            if (getMessengerId()) {
                clientSendChannel = pusher.subscribe(`${channelName}.${getMessengerId()}`);
                clientListenChannel = pusher.subscribe(`${channelName}.${auth_id}`);
            }

        }
        initClientChannel();

        channel.bind("video-call", function (data) {
            if (confirm("Video Call Form " + data.from.name)){
                window.location.replace(data.url);
                clientSendChannel.trigger("client-video-accept", {
                    url: $("meta[name=base]").attr("content") + '/profile/video/' + auth_id + '/video/join',
                });

            } else {
                clientSendChannel.trigger("client-video-cancel", {
                    from_id: auth_id, // Me
                    to_id: data.from.id, // Messenger
                    status: false,
                });
            }
        });

        channel.bind("audio-call", function (data) {
            if (confirm("Audio Call Form " + data.from.name)){
                window.location.replace(data.url);
                clientSendChannel.trigger("client-audio-accept", {
                    url: $("meta[name=base]").attr("content") + '/profile/video/' + auth_id + '/audio/join',
                });

            } else {
                clientSendChannel.trigger("client-audio-cancel", {
                    from_id: auth_id, // Me
                    to_id: data.from.id, // Messenger
                    status: false,
                });
            }
        });

        channel.bind("client-video-accept", function (data) {
            window.location.replace(data.url);
        });

        channel.bind("client-audio-accept", function (data) {
            window.location.replace(data.url);
        });

// Listen to messages, and append if data received
        channel.bind("messaging", function (data) {
            if (data.from_id == getMessengerId() && data.to_id == auth_id) {
                $(".messages").find(".message-hint").remove();
                messagesContainer.find(".messages").append(data.message);
                scrollToBottom(messagesContainer);
                makeSeen(true);
                // remove unseen counter for the user from the contacts list
                $(".messenger-list-item[data-contact=" + getMessengerId() + "]")
                    .find("tr>td>b")
                    .remove();
            }
        });

        if(clientListenChannel){
            // listen to typing indicator
            clientListenChannel.bind("client-typing", function (data) {
                if (data.from_id == getMessengerId() && data.to_id == auth_id) {
                    data.typing == true
                        ? messagesContainer.find(".typing-indicator").show()
                        : messagesContainer.find(".typing-indicator").hide();
                }
                // scroll to bottom
                scrollToBottom(messagesContainer);
            });



// listen to seen event
            clientListenChannel.bind("client-seen", function (data) {
                if (data.from_id == getMessengerId() && data.to_id == auth_id) {
                    if (data.seen == true) {
                        $(".message-time")
                            .find(".fa-check")
                            .before('<span class="fas fa-check-double seen"></span> ');
                        $(".message-time").find(".fa-check").remove();
                    }
                }
            });

// listen to contact item updates event
            clientListenChannel.bind("client-contactItem", function (data) {
                console.log('update here!');

                if (data.update_for == auth_id) {
                    data.updating == true
                        ? updateContactItem(data.update_to)
                        : console.error("[Contact Item updates] Updating failed!");
                }
            });

// listen on message delete event
            clientListenChannel.bind("client-messageDelete", function (data) {
                $("body").find(`.message-card[data-id=${data.id}]`).remove();
            });

        }


// -------------------------------------
// presence channel [User Active Status]
        var activeStatusChannel = pusher.subscribe("presence-activeStatus");

// Joined
        activeStatusChannel.bind("pusher:member_added", function (member) {
            setActiveStatus(1, member.id);
            $(".messenger-list-item[data-contact=" + member.id + "]")
                .find(".activeStatus")
                .remove();
            $(".messenger-list-item[data-contact=" + member.id + "]")
                .find(".avatar")
                .before(activeStatusCircle());
        });

// Leaved
        activeStatusChannel.bind("pusher:member_removed", function (member) {
            setActiveStatus(0, member.id);
            $(".messenger-list-item[data-contact=" + member.id + "]")
                .find(".activeStatus")
                .remove();
        });

        function handleVisibilityChange() {
            if (!document.hidden) {
                makeSeen(true);
            }
        }

        document.addEventListener("visibilitychange", handleVisibilityChange, false);

        /**
         *-------------------------------------------------------------
         * Trigger typing event
         *-------------------------------------------------------------
         */
        function isTyping(status) {
            if(clientSendChannel){
                return clientSendChannel.trigger("client-typing", {
                    from_id: auth_id, // Me
                    to_id: getMessengerId(), // Messenger
                    typing: status,
                });
            }
        }

        /**
         *-------------------------------------------------------------
         * Trigger seen event
         *-------------------------------------------------------------
         */
        function makeSeen(status) {
            if (document?.hidden) {
                return;
            }
            // remove unseen counter for the user from the contacts list
            $(".messenger-list-item[data-contact=" + getMessengerId() + "]")
                .find("tr>td>b")
                .remove();
            // seen
            $.ajax({
                url: url + "/makeSeen",
                method: "POST",
                data: { _token: access_token, id: getMessengerId() },
                dataType: "JSON",
            });
            if(clientSendChannel){
                return clientSendChannel.trigger("client-seen", {
                    from_id: auth_id, // Me
                    to_id: getMessengerId(), // Messenger
                    seen: status,
                });
            }

        }

        /**
         *-------------------------------------------------------------
         * Trigger contact item updates
         *-------------------------------------------------------------
         */
        function sendContactItemUpdates(status) {
            return clientSendChannel.trigger("client-contactItem", {
                update_for: getMessengerId(), // Messenger
                update_to: auth_id, // Me
                updating: status,
            });
        }

        /**
         *-------------------------------------------------------------
         * Trigger message delete
         *-------------------------------------------------------------
         */
        function sendMessageDeleteEvent(messageId) {
            return clientSendChannel.trigger("client-messageDelete", {
                id: messageId,
            });
        }

        /**
         *-------------------------------------------------------------
         * Check internet connection using pusher states
         *-------------------------------------------------------------
         */
        function checkInternet(state, selector) {
            let net_errs = 0;
            const messengerTitle = $(".messenger-headTitle");
            switch (state) {
                case "connected":
                    if (net_errs < 1) {
                        messengerTitle.text(messengerTitleDefault);
                        selector.addClass("successBG-rgba");
                        selector.find("span").hide();
                        selector.slideDown("fast", function () {
                            selector.find(".ic-connected").show();
                        });
                        setTimeout(function () {
                            $(".internet-connection").slideUp("fast");
                        }, 3000);
                    }
                    break;
                case "connecting":
                    messengerTitle.text($(".ic-connecting").text());
                    selector.removeClass("successBG-rgba");
                    selector.find("span").hide();
                    selector.slideDown("fast", function () {
                        selector.find(".ic-connecting").show();
                    });
                    net_errs = 1;
                    break;
                // Not connected
                default:
                    messengerTitle.text($(".ic-noInternet").text());
                    selector.removeClass("successBG-rgba");
                    selector.find("span").hide();
                    selector.slideDown("fast", function () {
                        selector.find(".ic-noInternet").show();
                    });
                    net_errs = 1;
                    break;
            }
        }

        /**
         *-------------------------------------------------------------
         * Get contacts
         *-------------------------------------------------------------
         */
        let contactsPage = 1;
        let contactsLoading = false;
        let noMoreContacts = false;
        function setContactsLoading(loading = false) {
            if (!loading) {
                $(".listOfContacts").find(".loading-contacts").remove();
            } else {
                $(".listOfContacts").append(
                    `<div class="loading-contacts">${listItemLoading(4)}</div>`
                );
            }
            contactsLoading = loading;
        }
        function getContacts() {
            if (!contactsLoading && !noMoreContacts) {
                setContactsLoading(true);
                $.ajax({
                    url: url + "/getContacts",
                    method: "GET",
                    data: { _token: access_token, page: contactsPage },
                    dataType: "JSON",
                    success: (data) => {
                        setContactsLoading(false);
                        if (contactsPage < 2) {
                            $(".listOfContacts").html(data.contacts);
                        } else {
                            $(".listOfContacts").append(data.contacts);
                        }
                        updateSelectedContact();
                        // update data-action required with [responsive design]
                        cssMediaQueries();
                        // Pagination lock & messages page
                        noMoreContacts = contactsPage >= data?.last_page;
                        if (!noMoreContacts) contactsPage += 1;
                    },
                    error: (error) => {
                        setContactsLoading(false);
                        console.error(error);
                    },
                });
            }
        }

        /**
         *-------------------------------------------------------------
         * Update contact item
         *-------------------------------------------------------------
         */
        function updateContactItem(user_id) {
            if (user_id != auth_id) {
                let listItem = $("body")
                    .find(".listOfContacts")
                    .find(".messenger-list-item[data-contact=" + user_id + "]");
                $.ajax({
                    url: url + "/updateContacts",
                    method: "POST",
                    data: {
                        _token: access_token,
                        user_id,
                    },
                    dataType: "JSON",
                    success: (data) => {
                        const totalContacts =
                            $(".listOfContacts").find(".messenger-list-item")?.length || 0;
                        if (totalContacts < 1)
                            $(".listOfContacts").find(".message-hint").remove();
                        listItem.remove();
                        $(".listOfContacts").prepend(data.contactItem);
                        // update data-action required with [responsive design]
                        cssMediaQueries();
                        updateSelectedContact(user_id);
                    },
                    error: () => {
                        console.error("Server error, check your response");
                    },
                });
            }
        }

        /**
         *-------------------------------------------------------------
         * Star
         *-------------------------------------------------------------
         */

        function star(user_id) {
            if (getMessengerId() != auth_id) {
                $.ajax({
                    url: url + "/star",
                    method: "POST",
                    data: { _token: access_token, user_id: user_id },
                    dataType: "JSON",
                    success: (data) => {
                        data.status > 0
                            ? $(".add-to-favorite").addClass("favorite")
                            : $(".add-to-favorite").removeClass("favorite");
                    },
                    error: () => {
                        console.error("Server error, check your response");
                    },
                });
            }
        }

        /**
         *-------------------------------------------------------------
         * Get favorite list
         *-------------------------------------------------------------
         */
        function getFavoritesList() {
            $(".messenger-favorites").html(avatarLoading(4));
            $.ajax({
                url: url + "/favorites",
                method: "POST",
                data: { _token: access_token },
                dataType: "JSON",
                success: (data) => {
                    if (data.count > 0) {
                        $(".favorites-section").show();
                        $(".messenger-favorites").html(data.favorites);
                    } else {
                        $(".favorites-section").hide();
                    }
                    // update data-action required with [responsive design]
                    cssMediaQueries();
                },
                error: () => {
                    console.error("Server error, check your response");
                },
            });
        }

        /**
         *-------------------------------------------------------------
         * Get shared photos
         *-------------------------------------------------------------
         */
        function getSharedPhotos(user_id) {
            $.ajax({
                url: url + "/shared",
                method: "POST",
                data: { _token: access_token, user_id: user_id },
                dataType: "JSON",
                success: (data) => {
                    $(".shared-photos-list").html(data.shared);
                },
                error: () => {
                    console.error("Server error, check your response");
                },
            });
        }

        /**
         *-------------------------------------------------------------
         * Search in messenger
         *-------------------------------------------------------------
         */
        let searchPage = 1;
        let noMoreDataSearch = false;
        let searchLoading = false;
        let searchTempVal = "";
        function setSearchLoading(loading = false) {
            if (!loading) {
                $(".search-records").find(".loading-search").remove();
            } else {
                $(".search-records").append(
                    `<div class="loading-search">${listItemLoading(4)}</div>`
                );
            }
            searchLoading = loading;
        }
        function messengerSearch(input) {
            if (input != searchTempVal) {
                searchPage = 1;
                noMoreDataSearch = false;
                searchLoading = false;
            }
            searchTempVal = input;
            if (!searchLoading && !noMoreDataSearch) {
                if (searchPage < 2) {
                    $(".search-records").html("");
                }
                setSearchLoading(true);
                $.ajax({
                    url: url + "/search",
                    method: "GET",
                    data: { _token: access_token, input: input, page: searchPage },
                    dataType: "JSON",
                    success: (data) => {
                        setSearchLoading(false);
                        if (searchPage < 2) {
                            $(".search-records").html(data.records);
                        } else {
                            $(".search-records").append(data.records);
                        }
                        // update data-action required with [responsive design]
                        cssMediaQueries();
                        // Pagination lock & messages page
                        noMoreDataSearch = searchPage >= data?.last_page;
                        if (!noMoreDataSearch) searchPage += 1;
                    },
                    error: (error) => {
                        setSearchLoading(false);
                        console.error(error);
                    },
                });
            }
        }

        /**
         *-------------------------------------------------------------
         * Delete Conversation
         *-------------------------------------------------------------
         */
        function deleteConversation(id) {
            $.ajax({
                url: url + "/deleteConversation",
                method: "POST",
                data: { _token: access_token, id: id },
                dataType: "JSON",
                beforeSend: () => {
                    // hide delete modal
                    app_modal({
                        show: false,
                        name: "delete",
                    });
                    // Show waiting alert modal
                    app_modal({
                        show: true,
                        name: "alert",
                        buttons: false,
                        body: loadingSVG("32px", null, "margin:auto"),
                    });
                },
                success: (data) => {
                    // delete contact from the list
                    $(".listOfContacts")
                        .find(".messenger-list-item[data-contact=" + id + "]")
                        .remove();
                    // refresh info
                    IDinfo(id, getMessengerType());

                    if (!data.deleted)
                        console.error("Error occurred, messages can not be deleted!");

                    // Hide waiting alert modal
                    app_modal({
                        show: false,
                        name: "alert",
                        buttons: true,
                        body: "",
                    });
                },
                error: () => {
                    console.error("Server error, check your response");
                },
            });
        }

        /**
         *-------------------------------------------------------------
         * Delete Message By ID
         *-------------------------------------------------------------
         */
        function deleteMessage(id) {
            $.ajax({
                url: url + "/deleteMessage",
                method: "POST",
                data: { _token: access_token, id: id },
                dataType: "JSON",
                beforeSend: () => {
                    // hide delete modal
                    app_modal({
                        show: false,
                        name: "delete",
                    });
                    // Show waiting alert modal
                    app_modal({
                        show: true,
                        name: "alert",
                        buttons: false,
                        body: loadingSVG("32px", null, "margin:auto"),
                    });
                },
                success: (data) => {
                    $(".messages").find(`.message-card[data-id=${id}]`).remove();
                    if (!data.deleted)
                        console.error("Error occurred, message can not be deleted!");

                    sendMessageDeleteEvent(id);

                    // Hide waiting alert modal
                    app_modal({
                        show: false,
                        name: "alert",
                        buttons: true,
                        body: "",
                    });
                },
                error: () => {
                    console.error("Server error, check your response");
                },
            });
        }

        /**
         *-------------------------------------------------------------
         * Update Settings
         *-------------------------------------------------------------
         */
        function updateSettings() {
            const formData = new FormData($("#update-settings")[0]);
            if (messengerColor) {
                formData.append("messengerColor", messengerColor);
            }
            if (dark_mode) {
                formData.append("dark_mode", dark_mode);
            }
            $.ajax({
                url: url + "/updateSettings",
                method: "POST",
                data: formData,
                dataType: "JSON",
                processData: false,
                contentType: false,
                beforeSend: () => {
                    // close settings modal
                    app_modal({
                        show: false,
                        name: "settings",
                    });
                    // Show waiting alert modal
                    app_modal({
                        show: true,
                        name: "alert",
                        buttons: false,
                        body: loadingSVG("32px", null, "margin:auto"),
                    });
                },
                success: (data) => {
                    if (data.error) {
                        // Show error message in alert modal
                        app_modal({
                            show: true,
                            name: "alert",
                            buttons: true,
                            body: data.msg,
                        });
                    } else {
                        // Hide alert modal
                        app_modal({
                            show: false,
                            name: "alert",
                            buttons: true,
                            body: "",
                        });

                        // reload the page
                        location.reload(true);
                    }
                },
                error: () => {
                    console.error("Server error, check your response");
                },
            });
        }

        /**
         *-------------------------------------------------------------
         * Set Active status
         *-------------------------------------------------------------
         */
        function setActiveStatus(status, user_id) {
            $.ajax({
                url: url + "/setActiveStatus",
                method: "POST",
                data: { _token: access_token, user_id: user_id, status: status },
                dataType: "JSON",
                success: (data) => {
                    // Nothing to do
                },
                error: () => {
                    console.error("Server error, check your response");
                },
            });
        }

        /**
         *-------------------------------------------------------------
         * On DOM ready
         *-------------------------------------------------------------
         */
        $(document).ready(function () {
            // get contacts list
            getContacts();

            // get contacts list
            getFavoritesList();

            // Clear typing timeout
            clearTimeout(typingTimeout);

            // NProgress configurations
            NProgress.configure({ showSpinner: false, minimum: 0.7, speed: 500 });

            // make message input autosize.
            autosize($(".m-send"));

            // check if pusher has access to the channel [Internet status]
            pusher.connection.bind("state_change", function (states) {
                let selector = $(".internet-connection");
                checkInternet(states.current, selector);
                // listening for pusher:subscription_succeeded
                channel.bind("pusher:subscription_succeeded", function () {
                    // On connection state change [Updating] and get [info & msgs]
                    if (getMessengerId() != 0) {
                        if (
                            $(".messenger-list-item")
                                .find("tr[data-action]")
                                .attr("data-action") == "1"
                        ) {
                            $(".messenger-listView").hide();
                        }
                        IDinfo(getMessengerId(), getMessengerType());
                    }
                });
            });

            // tabs on click, show/hide...
            $(".messenger-listView-tabs a").on("click", function () {
                var dataView = $(this).attr("data-view");
                $(".messenger-listView-tabs a").removeClass("active-tab");
                $(this).addClass("active-tab");
                $(".messenger-tab").hide();
                $(".messenger-tab[data-view=" + dataView + "]").show();
            });

            // set item active on click
            $("body").on("click", ".messenger-list-item", function () {
                const dataView = $(".messenger-list-item")
                    .find("p[data-type]")
                    .attr("data-type");
                $(".messenger-tab").hide();
                $(".messenger-tab[data-view=" + dataView + "s]").show();

                $(".messenger-list-item").removeClass("m-list-active");
                $(this).addClass("m-list-active");
                const userID = $(this).attr("data-contact");
                routerPush(document.title, `${url}/${userID}`);
                updateSelectedContact(userID);
            });

            // show info side button
            $(".messenger-infoView nav a , .show-infoSide").on("click", function () {
                $(".messenger-infoView").toggle();
            });

            // make favorites card dragable on click to slide.
            hScroller(".messenger-favorites");

            // click action for list item [user/group]
            $("body").on("click", ".messenger-list-item", function () {
                if ($(this).find("tr[data-action]").attr("data-action") == "1") {
                    $(".messenger-listView").hide();
                }
                const dataId = $(this).find("p[data-id]").attr("data-id");
                const dataType = $(this).find("p[data-type]").attr("data-type");
                setMessengerId(dataId);
                setMessengerType(dataType);
                IDinfo(dataId, dataType);
            });

            const getUserByID = $("meta[name=id]").attr("content");


            if(getUserByID){
                setMessengerId(getUserByID);
                setMessengerType('user');
                IDinfo(getUserByID, 'user');
            }

            // click action for favorite button
            $("body").on("click", ".favorite-list-item", function () {
                if ($(this).find("div").attr("data-action") == "1") {
                    $(".messenger-listView").hide();
                }
                const uid = $(this).find("div.avatar").attr("data-id");
                setMessengerId(uid);
                setMessengerType("user");
                IDinfo(uid, "user");
                updateSelectedContact(uid);
                routerPush(document.title, `${url}/${uid}`);
            });

            // list view buttons
            $(".listView-x").on("click", function () {
                $(".messenger-listView").hide();
            });
            $(".show-listView").on("click", function () {
                $(".messenger-listView").show();
            });

            // click action for [add to favorite] button.
            $(".add-to-favorite").on("click", function () {
                star(getMessengerId());
            });

            // calling Css Media Queries
            cssMediaQueries();

            // message form on submit.
            $("#message-form").on("submit", (e) => {
                e.preventDefault();
                sendMessage();
            });

            // message input on keyup [Enter to send, Enter+Shift for new line]
            $("#message-form .m-send").on("keyup", (e) => {
                // if enter key pressed.
                if (e.which == 13 || e.keyCode == 13) {
                    // if shift + enter key pressed, do nothing (new line).
                    // if only enter key pressed, send message.
                    if (!e.shiftKey) {
                        isTyping(false);
                        sendMessage();
                    }
                }
            });

            // On [upload attachment] input change, show a preview of the image/file.
            $("body").on("change", ".upload-attachment", (e) => {
                let file = e.target.files[0];
                if (!attachmentValidate(file)) return false;
                let reader = new FileReader();
                let sendCard = $(".messenger-sendCard");
                reader.readAsDataURL(file);
                reader.addEventListener("loadstart", (e) => {
                    $("#message-form").before(loadingSVG());
                });
                reader.addEventListener("load", (e) => {
                    $(".messenger-sendCard").find(".loadingSVG").remove();
                    if (!file.type.match("image.*")) {
                        // if the file not image
                        sendCard.find(".attachment-preview").remove(); // older one
                        sendCard.prepend(attachmentTemplate("file", file.name));
                    } else {
                        // if the file is an image
                        sendCard.find(".attachment-preview").remove(); // older one
                        sendCard.prepend(
                            attachmentTemplate("image", file.name, e.target.result)
                        );
                    }
                });
            });

            function attachmentValidate(file) {
                const fileElement = $(".upload-attachment");
                const { name: fileName, size: fileSize } = file;
                const fileExtension = fileName.split(".").pop();
                if (
                    !getAllowedExtensions.includes(fileExtension.toString().toLowerCase())
                ) {
                    alert("file type not allowed");
                    fileElement.val("");
                    return false;
                }
                // Validate file size.
                if (fileSize > getMaxUploadSize) {
                    alert("File is too large!");
                    return false;
                }
                return true;
            }

            // Attachment preview cancel button.
            $("body").on("click", ".attachment-preview .cancel", () => {
                cancelAttachment();
            });

            // typing indicator on [input] keyDown
            $("#message-form .m-send").on("keydown", () => {
                if (typingNow < 1) {
                    isTyping(true);
                    typingNow = 1;
                }
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(function () {
                    isTyping(false);
                    typingNow = 0;
                }, 1000);
            });

            // Image modal
            $("body").on("click", ".chat-image", function () {
                let src = $(this).css("background-image").split(/"/)[1];
                $("#imageModalBox").show();
                $("#imageModalBoxSrc").attr("src", src);
            });
            $(".imageModal-close").on("click", function () {
                $("#imageModalBox").hide();
            });

            // Search input on focus
            $(".messenger-search").on("focus", function () {
                $(".messenger-tab").hide();
                $('.messenger-tab[data-view="search"]').show();
            });
            $(".messenger-search").on("blur", function () {
                setTimeout(function () {
                    $(".messenger-tab").hide();
                    $('.messenger-tab[data-view="users"]').show();
                }, 200);
            });
            // Search action on keyup
            $(".messenger-search").on("keyup", function (e) {
                $.trim($(this).val()).length > 0
                    ? $(".messenger-search").trigger("focus") + messengerSearch($(this).val())
                    : $(".messenger-tab").hide() +
                    $('.messenger-listView-tabs a[data-view="users"]').trigger("click");
            });

            // Delete Conversation button
            $(".messenger-infoView-btns .delete-conversation").on("click", function () {
                app_modal({
                    name: "delete",
                });
            });
            // Delete Message Button
            $("body").on("click", ".chatify-hover-delete-btn", function () {
                app_modal({
                    name: "delete",
                    data: $(this).data("id"),
                });
            });
            // Delete modal [on delete button click]
            $(".app-modal[data-name=delete]")
                .find(".app-modal-footer .delete")
                .on("click", function () {
                    const id = $("body")
                        .find(".app-modal[data-name=delete]")
                        .find(".app-modal-card")
                        .attr("data-modal");
                    if (id == 0) {
                        deleteConversation(getMessengerId());
                    } else {
                        deleteMessage(id);
                    }
                    app_modal({
                        show: false,
                        name: "delete",
                    });
                });
            // delete modal [cancel button]
            $(".app-modal[data-name=delete]")
                .find(".app-modal-footer .cancel")
                .on("click", function () {
                    app_modal({
                        show: false,
                        name: "delete",
                    });
                });

            // Settings button action to show settings modal
            $("body").on("click", ".settings-btn", function (e) {
                e.preventDefault();
                app_modal({
                    show: true,
                    name: "settings",
                });
            });

            // on submit settings' form
            $("#update-settings").on("submit", (e) => {
                e.preventDefault();
                updateSettings();
            });
            // Settings modal [cancel button]
            $(".app-modal[data-name=settings]")
                .find(".app-modal-footer .cancel")
                .on("click", function () {
                    app_modal({
                        show: false,
                        name: "settings",
                    });
                    cancelUpdatingAvatar();
                });
            // upload avatar on change
            $("body").on("change", ".upload-avatar", (e) => {
                // store the original avatar
                if (defaultAvatarInSettings == null) {
                    defaultAvatarInSettings = $(".upload-avatar-preview").css(
                        "background-image"
                    );
                }
                let file = e.target.files[0];
                if (!attachmentValidate(file)) return false;
                let reader = new FileReader();
                reader.readAsDataURL(file);
                reader.addEventListener("loadstart", (e) => {
                    $(".upload-avatar-preview").append(
                        loadingSVG("42px", "upload-avatar-loading")
                    );
                });
                reader.addEventListener("load", (e) => {
                    $(".upload-avatar-preview").find(".loadingSVG").remove();
                    if (!file.type.match("image.*")) {
                        // if the file is not an image
                        console.error("File you selected is not an image!");
                    } else {
                        // if the file is an image
                        $(".upload-avatar-preview").css(
                            "background-image",
                            'url("' + e.target.result + '")'
                        );
                    }
                });
            });
            // change messenger color button
            $("body").on("click", ".update-messengerColor .color-btn", function () {
                messengerColor = $(this).attr("data-color");
                $(".update-messengerColor .color-btn").removeClass("m-color-active");
                $(this).addClass("m-color-active");
            });
            // Switch to Dark/Light mode
            $("body").on("click", ".dark-mode-switch", function () {
                if ($(this).attr("data-mode") == "0") {
                    $(this).attr("data-mode", "1");
                    $(this).removeClass("far");
                    $(this).addClass("fas");
                    dark_mode = "dark";
                } else {
                    $(this).attr("data-mode", "0");
                    $(this).removeClass("fas");
                    $(this).addClass("far");
                    dark_mode = "light";
                }
            });

            //Messages pagination
            actionOnScroll(
                ".m-body.messages-container",
                function () {
                    fetchMessages(getMessengerId(), getMessengerType());
                },
                true
            );
            //Contacts (users) pagination
            actionOnScroll(".messenger-tab.users-tab", function () {
                getContacts();
            });
            //Search pagination
            actionOnScroll(".messenger-tab.search-tab", function () {
                messengerSearch($(".messenger-search").val());
            });
        });

        /**
         *-------------------------------------------------------------
         * Observer on DOM changes
         *-------------------------------------------------------------
         */
        let previousMessengerId = getMessengerId();
        const observer = new MutationObserver(function (mutations) {
            if (getMessengerId() !== previousMessengerId) {
                previousMessengerId = getMessengerId();
                initClientChannel();
            }
        });
        const config = { subtree: true, childList: true };

// start listening to changes
        observer.observe(document, config);

// stop listening to changes
// observer.disconnect();

    }
}
</script>
