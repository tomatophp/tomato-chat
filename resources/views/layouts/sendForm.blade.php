<div class="messenger-sendCard">
    <form id="message-form" method="POST" action="{{ route(config('tomato-chat.routes.name').'send.message') }}" enctype="multipart/form-data">
        @csrf
        <div class="flex flex-col justify-center mx-2">
            <label><span class="bx bx-paperclip bx-sm"></span><input disabled='disabled' type="file" class="upload-attachment" name="file" accept=".{{implode(', .',config('tomato-chat.attachments.allowed_images'))}}, .{{implode(', .',config('tomato-chat.attachments.allowed_files'))}}" /></label>
        </div>
        <textarea readonly='readonly' name="message" class="m-send app-scroll" placeholder="Type a message.."></textarea>

        <div class="flex flex-col justify-center mx-2">
            <button disabled='disabled'><span class="bx bxs-send bx-sm"></span></button>
        </div>
    </form>
</div>
