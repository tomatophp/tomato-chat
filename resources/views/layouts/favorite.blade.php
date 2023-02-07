<div class="favorite-list-item">
    @php
        $favUser = \TomatoPHP\TomatoChat\Facades\TomatoChatMessenger::getUserWithAvatar($user);
        $email = trim($favUser->email); // "MyEmailAddress@example.com"
        $email = strtolower( $email ); // "myemailaddress@example.com"
        $token = md5( $email );
    @endphp
    <div data-id="{{ $user->id }}" data-action="0" class="avatar av-m"
        style="background-image: url('{{ \Illuminate\Support\Str::contains($favUser->avatar, ['jpg', 'png', 'gif', 'svg', 'jpeg', 'webp']) ? $favUser->avatar : 'https://www.gravatar.com/avatar/'.$token  }}');">
    </div>
    <p>{{ strlen($user->name) > 5 ? substr($user->name,0,6).'..' : $user->name }}</p>
</div>
