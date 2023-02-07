<x-tomato-admin-layout>
    <x-slot name="header">
        Chat
    </x-slot>
    {{-- Messenger Color Style--}}
    <div class="mb-4">
        <Chat>
            <div class="messenger h-screen">
                {{-- ----------------------Users/Groups lists side---------------------- --}}
                <div class="messenger-listView">
                    {{-- Header and search bar --}}
                    <div class="m-header">
                        <nav>
                            <a href="#"><i class="bx bxs-inbox"></i> <span class="messenger-headTitle">MESSAGES</span> </a>
                            {{-- header buttons --}}
                            <nav class="m-header-right">
                                <a href="#"><i class="bx bx-cog settings-btn"></i></a>
                                <a href="#" class="listView-x"><i class="bx bx-times"></i></a>
                            </nav>
                        </nav>
                        {{-- Search input --}}
                        <input type="text" class="messenger-search" placeholder="Search" />
                    </div>
                    {{-- tabs and lists --}}
                    <div class="m-body contacts-container">
                        {{-- Lists [Users/Group] --}}
                        {{-- ---------------- [ User Tab ] ---------------- --}}
                        <div class="@if($type == 'user') show @endif messenger-tab users-tab app-scroll" data-view="users">

                            {{-- Favorites --}}
                            <div class="favorites-section">
                                <p class="messenger-title">Favorites</p>
                                <div class="messenger-favorites app-scroll-thin"></div>
                            </div>

                            {{-- Saved Messages --}}
                            {!! view('tomato-chat::layouts.listItem', ['get' => 'saved']) !!}

                            {{-- Contact --}}
                            <div class="listOfContacts" style="width: 100%;height: calc(100% - 200px);position: relative;"></div>

                        </div>

                        {{-- ---------------- [ Group Tab ] ---------------- --}}
                        <div class="@if($type == 'group') show @endif messenger-tab groups-tab app-scroll" data-view="groups">
                            {{-- items --}}
                            <p style="text-align: center;color:grey;margin-top:30px">
                                <a target="_blank" style="color:{{$messengerColor}};" href="https://tomato-chat.munafio.com/notes#groups-feature">Click here</a> for more info!
                            </p>
                        </div>

                        {{-- ---------------- [ Search Tab ] ---------------- --}}
                        <div class="messenger-tab search-tab app-scroll" data-view="search">
                            {{-- items --}}
                            <p class="messenger-title">Search</p>
                            <div class="search-records">
                                <p class="message-hint center-el"><span>Type to search..</span></p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ----------------------Messaging side---------------------- --}}
                <div class="messenger-messagingView">
                    {{-- header title [conversation name] amd buttons --}}
                    <div class="m-header m-header-messaging">
                        <nav class="chatify-d-flex chatify-justify-content-between chatify-align-items-center">
                            {{-- header back button, avatar and user name --}}
                            <div class="chatify-d-flex chatify-justify-content-between chatify-align-items-center">
                                <a href="#" class="show-listView"><i class="bx bx-arrow-from-left"></i></a>
                                <div class="avatar av-s header-avatar" style="margin: 0px 10px; margin-top: -5px; margin-bottom: -5px;">
                                </div>
                                <a href="#" class="user-name">{{ config('tomato-chat.name') }}</a>
                            </div>
                            {{-- header buttons --}}
                            <nav class="m-header-right flex">
                                @if(config('tomato-chat.video_chat'))
                                    <Link  class="call-buttons" href="{{route(config('tomato-chat.routes.name') . 'video.confirm', $id)}}"><i class="bx bxs-video"></i></Link>
                                @endif
                                @if(config('tomato-chat.audio_chat'))
                                    <Link  class="call-buttons" class="mx-2"  href="{{route(config('tomato-chat.routes.name') . 'audio.confirm', $id)}}"><i class="bx bxs-phone"></i></Link>
                                @endif
                                <a href="#" class="add-to-favorite"><i class="bx bx-star"></i></a>
                                <a class="mx-2" href="/"><i class="bx bx-home"></i></a>
                                <a href="#" class="show-infoSide"><i class="bx bx-info-circle"></i></a>
                            </nav>
                        </nav>
                    </div>
                    {{-- Internet connection --}}
                    <div class="internet-connection">
                        <span class="ic-connected">Connected</span>
                        <span class="ic-connecting">Connecting...</span>
                        <span class="ic-noInternet">No internet access</span>
                    </div>
                    {{-- Messaging area --}}
                    <div class="m-body messages-container app-scroll">
                        <div class="messages">
                            <p class="message-hint center-el"><span>Please select a chat to start messaging</span></p>
                        </div>
                        {{-- Typing indicator --}}
                        <div class="typing-indicator">
                            <div class="message-card typing">
                                <p>
                        <span class="typing-dots">
                            <span class="dot dot-1"></span>
                            <span class="dot dot-2"></span>
                            <span class="dot dot-3"></span>
                        </span>
                                </p>
                            </div>
                        </div>
                        {{-- Send Message Form --}}
                        @include('tomato-chat::layouts.sendForm')
                    </div>
                </div>
                {{-- ---------------------- Info side ---------------------- --}}
                <div class="messenger-infoView app-scroll">
                    {{-- nav actions --}}
                    <nav>
                        <a href="#"><i class="bx bx-x"></i></a>
                    </nav>
                    {!! view('tomato-chat::layouts.info')->render() !!}
                </div>
            </div>
            @include('tomato-chat::layouts.modals')
        </Chat>
    </div>
</x-tomato-admin-layout>
