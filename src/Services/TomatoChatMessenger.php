<?php

namespace TomatoPHP\TomatoChat\Services;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Pusher\ApiErrorException;
use Pusher\PusherException;
use TomatoPHP\TomatoChat\Models\ChMessage as Message;
use TomatoPHP\TomatoChat\Models\ChFavorite as Favorite;
use Illuminate\Support\Facades\Storage;
use Pusher\Pusher;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\File;

/**
 *
 */
class TomatoChatMessenger
{
    /**
     * @var Pusher
     */
    public $pusher;

    /**
     * Get max file's upload size in MB.
     *
     * @return int
     */
    public function getMaxUploadSize(): int
    {
        return config('tomato-chat.attachments.max_upload_size') * 1048576;
    }

    /**
     * @throws PusherException
     */
    public function __construct()
    {
        $this->pusher = new Pusher(
            auth_key: config('tomato-chat.pusher.key'),
            secret: config('tomato-chat.pusher.secret'),
            app_id: config('tomato-chat.pusher.app_id'),
            options: config('tomato-chat.pusher.options'),
        );
    }
    /**
     * This method returns the allowed image extensions
     * to attach with the message.
     *
     * @return array
     */
    public function getAllowedImages(): array
    {
        return config('tomato-chat.attachments.allowed_images');
    }

    /**
     * This method returns the allowed file extensions
     * to attach with the message.
     *
     * @return array
     */
    public function getAllowedFiles(): array
    {
        return config('tomato-chat.attachments.allowed_files');
    }

    /**
     * Returns an array contains messenger's colors
     *
     * @return array
     */
    public function getMessengerColors(): array
    {
        return config('tomato-chat.colors');
    }


    /**
     * @param $channel
     * @param $event
     * @param $data
     * @return object
     * @throws GuzzleException
     * @throws ApiErrorException
     * @throws PusherException
     */
    public function push($channel, $event, $data): object
    {
        return $this->pusher->trigger($channel, $event, $data);
    }


    /**
     * @param $requestUser
     * @param $authUser
     * @param $channelName
     * @param $socket_id
     * @return JsonResponse|string
     */
    public function pusherAuth($requestUser, $authUser, $channelName, $socket_id): JsonResponse|string
    {
        // Auth data
        $authData = json_encode([
            'user_id' => $authUser->id,
            'user_info' => [
                'name' => $authUser->name
            ]
        ]);
        // check if user authenticated
        if (Auth::check()) {
            if($requestUser->id == $authUser->id){
                return $this->pusher->socket_auth(
                    $channelName,
                    $socket_id,
                    $authData
                );
            }
            // if not authorized
            return response()->json(['message'=>'Unauthorized'], 401);
        }
        // if not authenticated
        return response()->json(['message'=>'Not authenticated'], 403);
    }


    /**
     * @param $id
     * @param $index
     * @return array
     */
    public function fetchMessage($id, $index = null): array
    {
        $attachment = null;
        $attachment_type = null;
        $attachment_title = null;

        $msg = Message::where('id', $id)->first();
        if(!$msg){
            return [];
        }

        if (isset($msg->attachment)) {
            $attachmentOBJ = json_decode($msg->attachment);
            $attachment = $attachmentOBJ->new_name;
            $attachment_title = htmlentities(trim($attachmentOBJ->old_name), ENT_QUOTES, 'UTF-8');

            $ext = pathinfo($attachment, PATHINFO_EXTENSION);
            $attachment_type = in_array($ext, $this->getAllowedImages()) ? 'image' : 'file';
        }

        return [
            'index' => $index,
            'id' => $msg->id,
            'from_id' => $msg->from_id,
            'to_id' => $msg->to_id,
            'message' => $msg->body,
            'attachment' => [$attachment, $attachment_title, $attachment_type],
            'time' => $msg->created_at->diffForHumans(),
            'fullTime' => $msg->created_at,
            'viewType' => ($msg->from_id == Auth::user()->id) ? 'sender' : 'default',
            'seen' => $msg->seen,
        ];
    }


    /**
     * @param $data
     * @param $viewType
     * @return string
     */
    public function messageCard($data, $viewType = null): string
    {
        if (!$data) {
            return '';
        }
        $data['viewType'] = ($viewType) ? $viewType : $data['viewType'];
        return view('tomato-chat::layouts.messageCard', $data)->render();
    }


    /**
     * @param $user_id
     * @return mixed
     */
    public function fetchMessagesQuery($user_id): mixed
    {
        return Message::where('from_id', Auth::user()->id)->where('to_id', $user_id)
                    ->orWhere('from_id', $user_id)->where('to_id', Auth::user()->id);
    }

    /**
     * create a new message to database
     *
     * @param array $data
     * @return void
     */
    public function newMessage(array $data): void
    {
        $message = new Message();
        $message->id = $data['id'];
        $message->type = $data['type'];
        $message->from_id = $data['from_id'];
        $message->to_id = $data['to_id'];
        $message->body = $data['body'];
        $message->attachment = $data['attachment'];
        $message->save();
    }

    /**
     * Make messages between the sender [Auth user] and
     * the receiver [User id] as seen.
     *
     * @param int|string $user_id
     * @return bool
     */
    public function makeSeen(int|string $user_id):bool
    {
        Message::where('from_id', $user_id)
                ->where('to_id', Auth::user()->id)
                ->where('seen', 0)
                ->update(['seen' => 1]);
        return 1;
    }

    /**
     * Get last message for a specific user
     *
     * @param int|string $user_id
     * @return Message|Collection|Builder|null
     */
    public function getLastMessageQuery($user_id): Message|Collection|Builder|null
    {
        return $this->fetchMessagesQuery($user_id)->latest()->first();
    }

    /**
     * Count Unseen messages
     *
     * @param int|string $user_id
     * @return int
     */
    public function countUnseenMessages($user_id): int
    {
        return Message::where('from_id', $user_id)->where('to_id', Auth::user()->id)->where('seen', 0)->count();
    }

    /**
     * Get user list's item data [Contact Itme]
     * (e.g. User data, Last message, Unseen Counter...)
     *
     * @param Model $user
     * @return string
     */
    public function getContactItem(Model $user): string
    {
        // get last message
        $lastMessage = $this->getLastMessageQuery($user->id);

        // Get Unseen messages counter
        $unseenCounter = $this->countUnseenMessages($user->id);

        return view('tomato-chat::layouts.listItem', [
            'get' => 'users',
            'user' => $this->getUserWithAvatar($user),
            'lastMessage' => $lastMessage,
            'unseenCounter' => $unseenCounter,
        ])->render();
    }

    /**
     * Get user with avatar (formatted).
     *
     * @param Model $user
     * @return Model
     */
    public function getUserWithAvatar(Model $user): Model
    {
        if ($user->avatar == 'avatar.png' && config('chatify.gravatar.enabled')) {
            $imageSize = config('chatify.gravatar.image_size');
            $imageset = config('chatify.gravatar.imageset');
            $user->avatar = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user->email))) . '?s=' . $imageSize . '&d=' . $imageset;
        } else {
            $user->avatar = self::getUserAvatarUrl($user->avatar);
        }
        return $user;
    }

    /**
     * Check if a user in the favorite list
     *
     * @param int|string $user_id
     * @return bool
     */
    public function inFavorite(int|string $user_id): bool
    {
        return Favorite::where('user_id', Auth::user()->id)
                        ->where('favorite_id', $user_id)->count() > 0
                        ? true : false;
    }

    /**
     * Make user in favorite list
     *
     * @param int|string $user_id
     * @param int $action
     * @return bool
     */
    public function makeInFavorite(int|string $user_id,int $action): bool
    {
        if ($action > 0) {
            // Star
            $star = new Favorite();
            $star->id = rand(9, 99999999);
            $star->user_id = Auth::user()->id;
            $star->favorite_id = $user_id;
            $star->save();
            return $star ? true : false;
        } else {
            // UnStar
            $star = Favorite::where('user_id', Auth::user()->id)->where('favorite_id', $user_id)->delete();
            return $star ? true : false;
        }
    }

    /**
     * Get shared photos of the conversation
     *
     * @param int|string $user_id
     * @return array
     */
    public function getSharedPhotos(int|string $user_id): array
    {
        $images = array(); // Default
        // Get messages
        $msgs = $this->fetchMessagesQuery($user_id)->orderBy('created_at', 'DESC');
        if ($msgs->count() > 0) {
            foreach ($msgs->get() as $msg) {
                // If message has attachment
                if ($msg->attachment) {
                    $attachment = json_decode($msg->attachment);
                    // determine the type of the attachment
                    in_array(pathinfo($attachment->new_name, PATHINFO_EXTENSION), $this->getAllowedImages())
                    ? array_push($images, $attachment->new_name) : '';
                }
            }
        }
        return $images;
    }

    /**
     * Delete Conversation
     *
     * @param int|string $user_id
     * @return bool
     */
    public function deleteConversation(int|string $user_id): bool
    {
        try {
            foreach ($this->fetchMessagesQuery($user_id)->get() as $msg) {
                // delete file attached if exist
                if (isset($msg->attachment)) {
                    $path = config('chatify.attachments.folder').'/'.json_decode($msg->attachment)->new_name;
                    if (self::storage()->exists($path)) {
                        self::storage()->delete($path);
                    }
                }
                // delete from database
                $msg->delete();
            }
            return 1;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Delete message by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteMessage(int $id): bool
    {
        try {
            $msg = Message::where('from_id', auth()->id())->where('id', $id)->firstOrFail();
                if (isset($msg->attachment)) {
                    // delete file attached if exist
                    $path = config('chatify.attachments.folder') . '/' . json_decode($msg->attachment)->new_name;
                    if (self::storage()->exists($path)) {
                        self::storage()->delete($path);
                    }
                    // delete from database
                    $msg->delete();
                } else {
                    return 0;
                }
            return 1;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Return a storage instance with disk name specified in the config.
     */
    public function storage(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('tomato-chat.storage_disk_name'));
    }

    /**
     * Get user avatar url.
     *
     * @param string|null $user_avatar_name
     * @return string
     */
    public function getUserAvatarUrl(string|null $user_avatar_name): string
    {
        return self::storage()->url(config('tomato-chat.user_avatar.folder') . '/' . $user_avatar_name);
    }

    /**
     * Get attachment's url.
     *
     * @param string $attachment_name
     * @return string
     */
    public function getAttachmentUrl(string $attachment_name): string
    {
        return self::storage()->url(config('tomato-chat.attachments.folder') . '/' . $attachment_name);
    }
}
