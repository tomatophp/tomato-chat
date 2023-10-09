<template>
    <slot></slot>
</template>
<script>
import Pusher from "pusher-js";
import {inject} from "vue";

export default {
    name: 'Call',
    props: {
        auth_id: {
            String,
            required: true
        },
        call_id: {
            String,
            required: true
        }
    },
    mounted() {
        const Splade = inject("$splade");

        Pusher.logToConsole = true;

        const pusher = new Pusher(import.meta.env.VITE_PUSHER_APP_KEY, {
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

        const channelName = "private-chatify";
        const channel = pusher.subscribe(`${channelName}.${this.auth_id}`);
        const clientSendChannel = pusher.subscribe(`${channelName}.${this.call_id}`);

        $('#cancel-video').on('click', function (){
            clientSendChannel.trigger("client-video-cancel", {
                from_id: this.auth_id, // Me
                to_id: this.call_id, // Messenger
                status: false,
            });
        })

        $('#cancel-audio').on('click', function (){
            clientSendChannel.trigger("client-audio-cancel", {
                from_id: this.auth_id, // Me
                to_id: this.call_id, // Messenger
                status: false,
            });
        })

        clientSendChannel.bind("client-video-accept", function (data) {
            Splade.visit(data.url);
        });

        clientSendChannel.bind("client-audio-accept", function (data) {
            Splade.visit(data.url);
        });

        channel.bind("client-video-accept", function (data) {
            Splade.visit(data.url);
        });

        channel.bind("client-audio-accept", function (data) {
            Splade.visit(data.url);
        });
    }
};
</script>
