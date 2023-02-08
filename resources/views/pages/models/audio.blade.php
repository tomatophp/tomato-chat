<div class="bg-gray-900 p-4 h-screen w-screen flex flex-col justify-center my-auto">
    <Call :auth_id="{{auth(config('tomato-chat.guard'))->user()->id}}" :caller_id="{{$user->id}}" #default>
        <div>
            @php
                $favUser = \TomatoPHP\TomatoChat\Facades\TomatoChatMessenger::getUserWithAvatar($user);
                $email = trim($user->email); // "MyEmailAddress@example.com"
                $email = strtolower( $email ); // "myemailaddress@example.com"
                $token = md5( $email );
                $avatar = \Illuminate\Support\Str::contains($favUser->avatar, ['.jpg', '.gif', '.jpeg','.png', '.svg', '.webp']) ? $favUser->avatar : 'https://www.gravatar.com/avatar/'.$token;
            @endphp
            <div class="bg-cover bg-center h-32 w-32 mx-auto my-4 rounded-full" style="background-image: url('{{$avatar}}'); background-repeat: no-repeat">

            </div>
            <h2 class="text-white text-xl text-center font-bold">{{$user->name}}</h2>
            <h1 class="text-white text-3xl my-4 text-center font-bold">Do You Went To Start a Audio Call?</h1>
            <x-splade-data default="{calling: false}">
                <div v-if="data.calling" class="flex flex-col justify-center mx-auto text-white text-center">
                    <Link href="{{url()->previous()}}" class="h-12 w-12 bg-red-500 text-center text-white rounded-full mx-auto my-2 p-2">
                    <i class="bx bx-sm bx-x mt-1"></i>
                    </Link>
                    <h1 class="text-center text-white text-3xl text-green-500">Ring....</h1>
                </div>
                <div v-else class="flex justify-center my-4">
                    <div class="flex flex-col justify-center text-center text-white">
                        <x-splade-form preserve-scroll stay  @submit="data.calling = true" action="{{route(config('tomato-chat.routes.name') .  'chat.index', [$user->id, 'audio'])}}" method="get">
                            <button type="submit"  class="h-12 w-12 bg-green-500 text-center text-white rounded-full mx-4  my-2 p-2">
                                <i class="bx bx-sm bxs-phone mt-1"></i>
                            </button>
                        </x-splade-form>
                        <h1>Call</h1>
                    </div>
                    <div class="flex flex-col justify-center text-center text-white">
                        <Link href="{{url()->previous()}}" id="cancel-video" class="h-12 w-12 bg-red-500 text-center text-white rounded-full mx-4 my-2 p-2">
                        <i class="bx bx-sm bx-x mt-1"></i>
                        </Link>
                        <h1>Cancel</h1>
                    </div>
                </div>
            </x-splade-data>
        </div>
    </Call>

</div>
