<?php

namespace TomatoPHP\TomatoChat\Http\Controllers;

use ProtoneMedia\Splade\Facades\SEO;
use TomatoPHP\TomatoChat\Facades\TomatoChatMessenger;
use TomatoPHP\TomatoChat\Models\ChMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use TomatoPHP\TomatoChat\Models\ChMessage as Message;
use TomatoPHP\TomatoChat\Models\ChFavorite as Favorite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Illuminate\Support\Str;

class MessagesController extends Controller
{
    protected $perPage = 30;
    protected $messengerFallbackColor = '#2180f3';


    /**
     * Authenticate the connection for pusher
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pusherAuth(Request $request)
    {
        $data = json_decode(TomatoChatMessenger::pusherAuth(
            $request->user(),
            auth(config('tomato-chat.guard'))->user(),
            $request['channel_name'],
            $request['socket_id']
        ));

        return $data;
    }

    /**
     * Returning the view of the app with the required data.
     *
     * @param int $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index($id = null)
    {


        $routeName= FacadesRequest::route()->getName();
        $type = in_array($routeName, ['user','group'])
            ? $routeName
            : 'user';

        $seo = new SEO();
        $seo::metaByName('id', $id ?: auth(config('tomato-chat.guard'))->user()->id);
        $seo::metaByName('type', $type);
        $seo::metaByName('auth', auth(config('tomato-chat.guard'))->user()->id);
        $seo::metaByName('base', url( config('tomato-chat.routes.prefix')));
        $seo::metaByName('pusher-auth', route(config('tomato-chat.routes.name')."pusher.auth"));
        $seo::metaByName('url',url( config('tomato-chat.routes.prefix')));
        $seo::metaByName('url-main', url(config('tomato-chat.routes.prefix')));
        $seo::metaByName('messenger-color', auth(config('tomato-chat.guard'))->user()->messenger_color ?? $this->messengerFallbackColor);


        if($id !== null && $id !== "idInfo"){

            $email = trim(config('tomato-chat.users_model')::find($id)->email);
            $email = strtolower( $email ); // "myemailaddress@example.com"
            $token = md5( $email );
            $seo::metaByName('avatar', "https://www.gravatar.com/avatar/".$token);


            //            $user = config('tomato-chat.users_model')::find($id);
//            $isFriend = auth(config('tomato-chat.guard'))->user()->isFriendWith($user);
//            if($isFriend){
//                return view('tomato-chat::pages.app', [
//                    'id' => $id,
//                    'type' => $type ?? 'user',
//                    'messengerColor' => auth(config('tomato-chat.guard'))->user()->messenger_color ?? $this->messengerFallbackColor,
//                    'dark_mode' => auth(config('tomato-chat.guard'))->user()->dark_mode < 1 ? 'light' : 'dark',
//                ]);
//            }
//            else {
//                return redirect()->to(url('profile/public/'.$user->phone));
//            }
            return view('tomato-chat::pages.app', [
                'id' => $id,
                'type' => $type ?? 'user',
                'messengerColor' => auth(config('tomato-chat.guard'))->user()->messenger_color ?? $this->messengerFallbackColor,
                'dark_mode' => auth(config('tomato-chat.guard'))->user()->dark_mode < 1 ? 'light' : 'dark'
            ]);

        }
        else {
            $email = trim(auth(config('tomato-chat.guard'))->user()->email);
            $email = strtolower( $email ); // "myemailaddress@example.com"
            $token = md5( $email );
            $seo::metaByName('avatar', "https://www.gravatar.com/avatar/".$token);

            return view('tomato-chat::pages.app', [
                'id' => 0,
                'type' => $type ?? 'user',
                'messengerColor' => auth(config('tomato-chat.guard'))->user()->messenger_color ?? $this->messengerFallbackColor,
                'dark_mode' => auth(config('tomato-chat.guard'))->user()->dark_mode < 1 ? 'light' : 'dark'
            ]);
        }

    }


    /**
     * Fetch data by id for (user/group)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function idFetchData(Request $request)
    {
        // Favorite
        $favorite = TomatoChatMessenger::inFavorite($request['id']);

        // User data
        if ($request['type'] == 'user') {
            $fetch = config('tomato-chat.users_model')::where('id', $request['id'])->first();
            if($fetch){
                $email = trim($fetch->email);
                $email = strtolower( $email ); // "myemailaddress@example.com"
                $token = md5( $email );
                $getAvatar = TomatoChatMessenger::getUserWithAvatar($fetch)->avatar;

                $userAvatar = Str::contains($getAvatar, ['.jpg', '.gif', '.jpeg','.png', '.svg', '.webp']) ? $getAvatar : "https://www.gravatar.com/avatar/".$token;
            }
        }

        // send the response
        return Response::json([
            'favorite' => $favorite,
            'fetch' => $fetch ?? [],
            'user_avatar' => $userAvatar ?? null,
        ]);
    }

    /**
     * This method to make a links for the attachments
     * to be downloadable.
     *
     * @param string $fileName
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|void
     */
    public function download($fileName)
    {
        if (TomatoChatMessenger::storage()->exists(config('tomato-chat.attachments.folder') . '/' . $fileName)) {
            return TomatoChatMessenger::storage()->download(config('tomato-chat.attachments.folder') . '/' . $fileName);
        } else {
            return abort(404, "Sorry, File does not exist in our server or may have been deleted!");
        }
    }

    /**
     * Send a message to database
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function send(Request $request)
    {
//        $featureValue = PlanFeature::where('key', 'message.count')
//            ->first()
//            ?
//            PlanFeature::where('key', 'message.count')
//                ->first()->plans
//            ->find(\auth(config('tomato-chat.guard'))
//                ->user()
//                ->activePlan()
//                ->plan_id
//            )
//            ->toArray()['pivot']['value'] ?? 0 : 0;
//        if((int)$featureValue){
//            $checkCountMessage = ChMessage::where('from_id', auth(config('tomato-chat.guard'))->user()->id)
//                ->where('to_id', $request['id'])
//                ->count();
//            if($checkCountMessage <= (int)$featureValue){
//                // default variables
//                $error = (object)[
//                    'status' => 0,
//                    'message' => null
//                ];
//                $attachment = null;
//                $attachment_title = null;
//
//                // if there is attachment [file]
//                if ($request->hasFile('file')) {
//                    // allowed extensions
//                    $allowed_images = TomatoChatMessenger::getAllowedImages();
//                    $allowed_files  = TomatoChatMessenger::getAllowedFiles();
//                    $allowed        = array_merge($allowed_images, $allowed_files);
//
//                    $file = $request->file('file');
//                    // check file size
//                    if ($file->getSize() < TomatoChatMessenger::getMaxUploadSize()) {
//                        if (in_array(strtolower($file->getClientOriginalExtension()), $allowed)) {
//                            // get attachment name
//                            $attachment_title = $file->getClientOriginalName();
//                            // upload attachment and store the new name
//                            $attachment = Str::uuid() . "." . $file->getClientOriginalExtension();
//                            $file->storeAs(config('chatify.attachments.folder'), $attachment, config('chatify.storage_disk_name'));
//                        } else {
//                            $error->status = 1;
//                            $error->message = "File extension not allowed!";
//                        }
//                    } else {
//                        $error->status = 1;
//                        $error->message = "File size you are trying to upload is too large!";
//                    }
//                }
//
//                if (!$error->status) {
//                    // send to database
//                    $messageID = mt_rand(9, 999999999) + time();
//                    TomatoChatMessenger::newMessage([
//                        'id' => $messageID,
//                        'type' => $request['type'],
//                        'from_id' => auth(config('tomato-chat.guard'))->user()->id,
//                        'to_id' => $request['id'],
//                        'body' => htmlentities(trim($request['message']), ENT_QUOTES, 'UTF-8'),
//                        'attachment' => ($attachment) ? json_encode((object)[
//                            'new_name' => $attachment,
//                            'old_name' => htmlentities(trim($attachment_title), ENT_QUOTES, 'UTF-8'),
//                        ]) : null,
//                    ]);
//
//                    // fetch message to send it with the response
//                    $messageData = TomatoChatMessenger::fetchMessage($messageID);
//
//                    // send to user using pusher
//                    TomatoChatMessenger::push("private-chatify.".$request['id'], 'messaging', [
//                        'from_id' => auth(config('tomato-chat.guard'))->user()->id,
//                        'to_id' => $request['id'],
//                        'message' => TomatoChatMessenger::messageCard($messageData, 'default')
//                    ]);
//                }
//
//                // send the response
//                return Response::json([
//                    'status' => '200',
//                    'error' => $error,
//                    'message' => TomatoChatMessenger::messageCard(@$messageData),
//                    'tempID' => $request['temporaryMsgId'],
//                ]);
//            }
//        }
        $checkCountMessage = ChMessage::where('from_id', auth(config('tomato-chat.guard'))->user()->id)
            ->where('to_id', $request['id'])
            ->count();
        // default variables
        $error = (object)[
            'status' => 0,
            'message' => null
        ];
        $attachment = null;
        $attachment_title = null;

        // if there is attachment [file]
        if ($request->hasFile('file')) {
            // allowed extensions
            $allowed_images = TomatoChatMessenger::getAllowedImages();
            $allowed_files  = TomatoChatMessenger::getAllowedFiles();
            $allowed        = array_merge($allowed_images, $allowed_files);

            $file = $request->file('file');
            // check file size
            if ($file->getSize() < TomatoChatMessenger::getMaxUploadSize()) {
                if (in_array(strtolower($file->getClientOriginalExtension()), $allowed)) {
                    // get attachment name
                    $attachment_title = $file->getClientOriginalName();
                    // upload attachment and store the new name
                    $attachment = Str::uuid() . "." . $file->getClientOriginalExtension();
                    $file->storeAs(config('tomato-chat.attachments.folder'), $attachment, config('tomato-chat.storage_disk_name'));
                } else {
                    $error->status = 1;
                    $error->message = "File extension not allowed!";
                }
            } else {
                $error->status = 1;
                $error->message = "File size you are trying to upload is too large!";
            }
        }

        if (!$error->status) {
            // send to database
            $messageID = mt_rand(9, 999999999) + time();
            TomatoChatMessenger::newMessage([
                'id' => $messageID,
                'type' => $request['type'],
                'from_id' => auth(config('tomato-chat.guard'))->user()->id,
                'to_id' => $request['id'],
                'body' => htmlentities(trim($request['message']), ENT_QUOTES, 'UTF-8'),
                'attachment' => ($attachment) ? json_encode((object)[
                    'new_name' => $attachment,
                    'old_name' => htmlentities(trim($attachment_title), ENT_QUOTES, 'UTF-8'),
                ]) : null,
            ]);

            // fetch message to send it with the response
            $messageData = TomatoChatMessenger::fetchMessage($messageID);

            // send to user using pusher
            TomatoChatMessenger::push("private-chatify.".$request['id'], 'messaging', [
                'from_id' => auth(config('tomato-chat.guard'))->user()->id,
                'to_id' => $request['id'],
                'message' => TomatoChatMessenger::messageCard($messageData, 'default')
            ]);
        }

        // send the response
        return Response::json([
            'status' => '200',
            'error' => $error,
            'message' => TomatoChatMessenger::messageCard(@$messageData),
            'tempID' => $request['temporaryMsgId'],
        ]);
    }

    /**
     * fetch [user/group] messages from database
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function fetch(Request $request)
    {
        $query = TomatoChatMessenger::fetchMessagesQuery($request['id'])->latest();
        $messages = $query->paginate($request->per_page ?? $this->perPage);
        $totalMessages = $messages->total();
        $lastPage = $messages->lastPage();
        $response = [
            'total' => $totalMessages,
            'last_page' => $lastPage,
            'last_message_id' => collect($messages->items())->last()->id ?? null,
            'messages' => '',
        ];

        // if there is no messages yet.
        if ($totalMessages < 1) {
            $response['messages'] ='<p class="message-hint center-el"><span>Say \'hi\' and start messaging</span></p>';
            return Response::json($response);
        }
        if (count($messages->items()) < 1) {
            $response['messages'] = '';
            return Response::json($response);
        }
        $allMessages = null;
        foreach ($messages->reverse() as $index => $message) {
            $allMessages .= TomatoChatMessenger::messageCard(
                TomatoChatMessenger::fetchMessage($message->id, $index)
            );
        }
        $response['messages'] = $allMessages;
        return Response::json($response);
    }

    /**
     * Make messages as seen
     *
     * @param Request $request
     * @return JsonResponse|void
     */
    public function seen(Request $request)
    {
        // make as seen
        $seen = false;
        if($request['id'] !== 'idInfo'){
            $seen = TomatoChatMessenger::makeSeen($request['id']);
        }

        // send the response
        return Response::json([
            'status' => $seen,
        ], 200);
    }

    /**
     * Get contacts list
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getContacts(Request $request)
    {
        // get all users that received/sent message from/to [Auth user]
        $users = Message::join(app(config('tomato-chat.users_model'))->getTable(),  function ($join) {
            $join->on('ch_messages.from_id', '=', app(config('tomato-chat.users_model'))->getTable().'.id')
                ->orOn('ch_messages.to_id', '=', app(config('tomato-chat.users_model'))->getTable().'.id');
        })
            ->where(function ($q) {
                $q->where('ch_messages.from_id', auth(config('tomato-chat.guard'))->user()->id)
                    ->orWhere('ch_messages.to_id', auth(config('tomato-chat.guard'))->user()->id);
            })
            ->where(app(config('tomato-chat.users_model'))->getTable().'.id', '!=', auth(config('tomato-chat.guard'))->user()->id)
            ->select(app(config('tomato-chat.users_model'))->getTable().'.*',DB::raw('MAX(ch_messages.created_at) max_created_at'))
            ->orderBy('max_created_at', 'desc')
            ->groupBy(app(config('tomato-chat.users_model'))->getTable().'.id')
            ->paginate($request->per_page ?? $this->perPage);

        $usersList = $users->items();

        if (count($usersList) > 0) {
            $contacts = '';
            foreach ($usersList as $user) {
                $contacts .= TomatoChatMessenger::getContactItem($user);
            }
        } else {
            $contacts = '<p class="message-hint center-el"><span>Your contact list is empty</span></p>';
        }

        return response()->json([
            'contacts' => utf8_encode($contacts),
            'total' => $users->total() ?? 0,
            'last_page' => $users->lastPage() ?? 1,
        ], 200 ,  ['Content-type'=> 'application/json; charset=utf-8'], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Update user's list item data
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateContactItem(Request $request)
    {
        // Get user data
        $user = config('tomato-chat.users_model')::where('id', $request['user_id'])->first();
        if(!$user){
            return Response::json([
                'message' => 'User not found!',
            ], 401);
        }
        $contactItem = TomatoChatMessenger::getContactItem($user);

        // send the response
        return Response::json([
            'contactItem' => $contactItem,
        ], 200);
    }

    /**
     * Put a user in the favorites list
     *
     * @param Request $request
     * @return JsonResponse|void
     */
    public function favorite(Request $request)
    {
        // check action [star/unstar]
        if (TomatoChatMessenger::inFavorite($request['user_id'])) {
            // UnStar
            TomatoChatMessenger::makeInFavorite($request['user_id'], 0);
            $status = 0;
        } else {
            // Star
            TomatoChatMessenger::makeInFavorite($request['user_id'], 1);
            $status = 1;
        }

        // send the response
        return Response::json([
            'status' => @$status,
        ], 200);
    }

    /**
     * Get favorites list
     *
     * @param Request $request
     * @return JsonResponse|void
     */
    public function getFavorites(Request $request)
    {
        $favoritesList = null;
        $favorites = Favorite::where('user_id', auth(config('tomato-chat.guard'))->user()->id);
        foreach ($favorites->get() as $favorite) {
            // get user data
            $user = config('tomato-chat.users_model')::where('id', $favorite->favorite_id)->first();
            $favoritesList .= view('tomato-chat::layouts.favorite', [
                'user' => $user,
            ]);
        }
        // send the response
        return Response::json([
            'count' => $favorites->count(),
            'favorites' => $favorites->count() > 0
                ? $favoritesList
                : 0,
        ], 200);
    }

    /**
     * Search in messenger
     *
     * @param Request $request
     * @return JsonResponse|void
     */
    public function search(Request $request)
    {
        $getRecords = null;
        $input = trim(filter_var($request['input']));
        $records = config('tomato-chat.users_model')::where('id','!=',auth(config('tomato-chat.guard'))->user()->id)
            ->where('name', 'LIKE', "%{$input}%")
            ->orWhere('email', 'LIKE', "%{$input}%")
            ->paginate($request->per_page ?? $this->perPage);

        foreach ($records->items() as $record) {
            $getRecords .= view('tomato-chat::layouts.listItem', [
                'get' => 'search_item',
                'type' => 'user',
                'user' => TomatoChatMessenger::getUserWithAvatar($record),
            ])->render();
        }
        if($records->total() < 1){
            $getRecords = '<p class="message-hint center-el"><span>Nothing to show.</span></p>';
        }
        // send the response
        return Response::json([
            'records' => $getRecords,
            'total' => $records->total(),
            'last_page' => $records->lastPage()
        ], 200,  ['Content-type'=> 'application/json; charset=utf-8'], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get shared photos
     *
     * @param Request $request
     * @return JsonResponse|void
     */
    public function sharedPhotos(Request $request)
    {
        $shared = TomatoChatMessenger::getSharedPhotos($request['user_id']);
        $sharedPhotos = null;

        // shared with its template
        for ($i = 0; $i < count($shared); $i++) {
            $sharedPhotos .= view('tomato-chat::layouts.listItem', [
                'get' => 'sharedPhoto',
                'image' => TomatoChatMessenger::getAttachmentUrl($shared[$i]),
            ])->render();
        }
        // send the response
        return Response::json([
            'shared' => count($shared) > 0 ? $sharedPhotos : '<p class="message-hint"><span>Nothing shared yet</span></p>',
        ], 200);
    }

    /**
     * Delete conversation
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteConversation(Request $request)
    {
        // delete
        $delete = TomatoChatMessenger::deleteConversation($request['id']);

        // send the response
        return Response::json([
            'deleted' => $delete ? 1 : 0,
        ], 200);
    }

    /**
     * Delete message
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteMessage(Request $request)
    {
        // delete
        $delete = TomatoChatMessenger::deleteMessage($request['id']);

        // send the response
        return Response::json([
            'deleted' => $delete ? 1 : 0,
        ], 200);
    }

    public function updateSettings(Request $request)
    {
        $msg = null;
        $error = $success = 0;

        // dark mode
        if ($request['dark_mode']) {
            $request['dark_mode'] == "dark"
                ? config('tomato-chat.users_model')::where('id', auth(config('tomato-chat.guard'))->user()->id)->update(['dark_mode' => 1])  // Make Dark
                : config('tomato-chat.users_model')::where('id', auth(config('tomato-chat.guard'))->user()->id)->update(['dark_mode' => 0]); // Make Light
        }

        // If messenger color selected
        if ($request['messengerColor']) {
            $messenger_color = trim(filter_var($request['messengerColor']));
            config('tomato-chat.users_model')::where('id', auth(config('tomato-chat.guard'))->user()->id)
                ->update(['messenger_color' => $messenger_color]);
        }
        // if there is a [file]
        if ($request->hasFile('avatar')) {
            // allowed extensions
            $allowed_images = TomatoChatMessenger::getAllowedImages();

            $file = $request->file('avatar');
            // check file size
            if ($file->getSize() < TomatoChatMessenger::getMaxUploadSize()) {
                if (in_array(strtolower($file->getClientOriginalExtension()), $allowed_images)) {
                    // delete the older one
                    if (auth(config('tomato-chat.guard'))->user()->avatar != config('tomato-chat.user_avatar.default')) {
                        $avatar = auth(config('tomato-chat.guard'))->user()->avatar;
                        if (TomatoChatMessenger::storage()->exists($avatar)) {
                            TomatoChatMessenger::storage()->delete($avatar);
                        }
                    }
                    // upload
                    $avatar = Str::uuid() . "." . $file->getClientOriginalExtension();
                    $update = config('tomato-chat.users_model')::where('id', auth(config('tomato-chat.guard'))->user()->id)->update(['avatar' => $avatar]);
                    $file->storeAs(config('tomato-chat.user_avatar.folder'), $avatar, config('tomato-chat.storage_disk_name'));
                    $success = $update ? 1 : 0;
                } else {
                    $msg = "File extension not allowed!";
                    $error = 1;
                }
            } else {
                $msg = "File size you are trying to upload is too large!";
                $error = 1;
            }
        }

        // send the response
        return Response::json([
            'status' => $success ? 1 : 0,
            'error' => $error ? 1 : 0,
            'message' => $error ? $msg : 0,
        ], 200);
    }

    /**
     * Set user's active status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function setActiveStatus(Request $request)
    {
        $update = $request['status'] > 0
            ? config('tomato-chat.users_model')::where('id', $request['user_id'])->update(['active_status' => 1])
            : config('tomato-chat.users_model')::where('id', $request['user_id'])->update(['active_status' => 0]);
        // send the response
        return Response::json([
            'status' => $update,
        ], 200);
    }
}
