<div class="bg-gray-900 p-4 h-screen w-screen flex flex-col justify-center my-auto">
    <Video url="{{route(config('tomato-chat.routes.name').'index')}}" :auth_id="{{auth(config('tomato-chat.guard'))->user()->id}}" :call_id="{{$user->id}}" #default>
        <div class="flex flex-col justify-center w-full">
            <input type="hidden" name="appID" id="appID" value="{{ $appID }}" />
            <input type="hidden" name="channelName" id="channelName" value="{{ $channelName }}" />
            <input type="hidden" name="agoraToken" id="agoraToken" value="{{ $token }}" />
            <input type="hidden" name="uid" id="uid" value="{{ $uid }}" />
            <input type="hidden" name="streamType" id="streamType" value="{{ $type }}" />

            <div class="my-4 flex flex-col justify-center">
                <div id="avatar">
                    @php
                        $favUser = \TomatoPHP\TomatoChat\Facades\TomatoChatMessenger::getUserWithAvatar($user);
                        $email = trim($user->email); // "MyEmailAddress@example.com"
                        $email = strtolower( $email ); // "myemailaddress@example.com"
                        $token = md5( $email );
                        $avatar = \Illuminate\Support\Str::contains($favUser->avatar, ['.jpg', '.gif', '.jpeg','.png', '.svg', '.webp']) ? $favUser->avatar : 'https://www.gravatar.com/avatar/'.$token;
                    @endphp
                    <div class="bg-cover bg-center h-32 w-32 mx-auto my-4 rounded-full" style="background-image: url('{{$avatar}}'); background-repeat: no-repeat">

                    </div>
                </div>
                <div id="streaming" class="my-4 mx-auto flex justify-center space-x-4">
                </div>
                <div class="flex justify-center my-4">
                    <div class="flex flex-col justify-center text-center text-white">
                        <a href="{{route(config('tomato-chat.routes.name').'index')}}"  id="leave" class="h-12 w-12 bg-red-500 text-center text-white rounded-full mx-auto my-2 p-2">
                            <i class="bx bx-sm bx-x mt-1"></i>
                        </a>
                        <h1>Cancel</h1>
                    </div>
                </div>
            </div>
        </div>
    </Video>
</div>
