@extends('admin')

@section('content')
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- /.col-md-6 -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <button type="button" onclick="getUsers()" class="btn btn-sm btn-success" data-toggle="modal" data-target="#exampleModalLong">
                                    <i class="fas fa-plus"></i> New Message
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <ul class="list-group mt-3" id="channelList">
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- /.col-md-6 -->

                <div class="col-lg-8">
                    <!-- DIRECT CHAT -->
                    <div class="card direct-chat direct-chat-primary">
                        <div class="card-header">
                            <h3 class="card-title">Direct Chat</h3>

                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <button type="button" class="btn btn-tool" title="Contacts"
                                        data-widget="chat-pane-toggle">
                                    <i class="fas fa-comments"></i>
                                </button>
                                <button type="button" class="btn btn-tool" data-card-widget="remove">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <!-- Conversations are loaded here -->
                            <div class="direct-chat-messages" id="messages">

                            </div>
                            <!-- /.direct-chat-pane -->
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer">
                                <div class="input-group">
                                    <input type="text" id="message" placeholder="Type Message ..."
                                           class="form-control">
                                    <span class="input-group-append">
                                        <button type="button" id="send" class="btn btn-primary">Send</button>
                                      </span>
                                </div>
                        </div>
                        <!-- /.card-footer-->
                    </div>
                    <!--/.direct-chat -->

                </div>

            </div>
            <!-- /.row -->

            <!-- Modal -->
            <div class="modal fade" id="exampleModalLong" tabindex="-1" role="dialog" aria-labelledby="exampleModalLongTitle" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLongTitle">Messages</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group" style="display: none;" id="name-group-form">
                                <label>Name Group</label>
                                <input type="text" class="form-control" id="name-group" placeholder="Enter Name Group">
                            </div>
                            <div class="form-group">
                                <label>Send To: </label>
                                <select id="users" class="form-control select2" multiple="multiple" data-placeholder="Select a State" style="width: 100%;">
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button id="create-channel" type="button" class="btn btn-primary">Create</button>
                        </div>
                    </div>
                </div>
            </div>


        </div><!-- /.container-fluid -->
    </div>
@endsection('content')
@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
@endsection('css')

@section('script')
    <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>

    <script>
        let socket;
        let userID = "{{ Auth::user()->id }}";
        let token = "{{ auth()->user()->token }}";
        let conversationCurrent;
        $(document).ready(function () {
            socket_server = "ws://{{ env('SOCKET_URL') }}:8090/"
            socket = new WebSocket(socket_server + '?token=' + token);

            socket.onopen = function (e) {
                getChannels(socket);
            }
            socket.onmessage = function (e) {
                let messages;
                const data = JSON.parse(e.data);
                const type = data.type;

                // receive message
                if (type == {{ \App\Enums\EnumChat::MESSAGE  }}) {
                    if(data.conversation_id == conversationCurrent) {
                        if (data.sender_id != userID) {
                            messages = MessageOther(data.name, data.created_at, data.avatar, data.text);
                        } else {
                            messages = MessageMe(data.name, data.created_at, data.avatar, data.text);
                        }
                        $('#messages').append(messages);
                        scrollMessage()
                    }
                }
                // receive channels
                if (type == {{ \App\Enums\EnumChat::CHANNEL  }}) {
                    let channels = data.channels;
                    let htmlChanel = '';
                    channels.forEach(function (data) {
                        var coutUser = data.conversation_user.length;
                        if (coutUser > 2) {
                            htmlChanel += `<li onclick="loadMessage(${data.id})" class="list-group-item">${data.name}</li>`;
                        }else if (coutUser == 2){
                            if(data.conversation_user[0].user_id == userID) {
                                htmlChanel += `<li onclick="loadMessage(${data.id})" class="list-group-item">
                                                ${data.conversation_user[1].user.name}
                                            </li>`;
                            }else {
                                htmlChanel += `<li onclick="loadMessage(${data.id})" class="list-group-item">
                                                ${data.conversation_user[0].user.name}
                                            </li>`;
                            }
                        }
                    })
                    $('#channelList').html(htmlChanel);
                }
                // load message
                if (type == {{ \App\Enums\EnumChat::LOAD_MESSAGE  }}) {
                    let messages = data.messages;
                    let htmlMessages = '';
                    messages.forEach(function (data) {
                        if (data.sender_id == userID) {
                            // right
                            htmlMessages += MessageMe(data.name, data.created_at, data.avatar, data.text)
                        } else {
                            htmlMessages += MessageOther(data.name, data.created_at, data.avatar, data.text)
                        }
                    })
                    $('#messages').html(htmlMessages);
                    scrollMessage();
                }
                // load users
                if (type == {{ \App\Enums\EnumChat::GET_USER  }}) {
                    let users = data.users;
                    let htmlUsers = '';
                    users.forEach(function (data) {
                        htmlUsers += `<option value="${data.id}">${data.name} (${data.online_status})</option>`
                    })
                    $('#users').html(htmlUsers);
                }

                if (type == {{ \App\Enums\EnumChat::CREATE_CHANNEL  }}) {
                    getChannels()
                    if (data.user_init_id == userID) {
                        loadMessage(data.conversation_id)
                    }
                }


            }

            // enter or click send message
            function processMessage() {
                const message = $('#message').val().trim();
                if (message !== '') {
                    sendMessage(message, conversationCurrent);
                    $('#message').val('');
                    scrollMessage();
                }
            }

            $('#message').keypress(function(event) {
                if (event.keyCode === 13) {
                    event.preventDefault();
                    processMessage();
                }
            });

            $('#send').click(function () {
                processMessage();
            });


            $('#create-channel').click(function (){
                const name = $('#name-group').val().trim();
                const ids = $('#users').val();
                if (message && ids) {
                    createChannel(name, ids);
                    $('#exampleModalLong').modal('hide')
                }
            });

            $('#users').change(function (){
                const ids = $('#users').val();
               if (ids.length > 1) {
                   $('#name-group-form').show();
               }else{
                   $('#name-group-form').hide();
               }
            })


        })

        function loadMessage(conversation_id) {
            conversationCurrent = conversation_id
            socket.send(JSON.stringify({
                conversation_id: conversation_id,
                action: {{ \App\Enums\EnumChat::LOAD_MESSAGE  }},
            }))

        }

        function sendMessage(message, conversation_id) {
            socket.send(JSON.stringify({
                conversation_id: conversation_id,
                text: message,
                action: {{ \App\Enums\EnumChat::MESSAGE  }},
            }))
        }

        function getChannels() {
            socket.send(JSON.stringify({
                action: {{ \App\Enums\EnumChat::CHANNEL  }},
            }))
        }

        function getUsers() {
            socket.send(JSON.stringify({
                action: {{ \App\Enums\EnumChat::GET_USER  }},
            }))
        }

        function createChannel(name, ids) {
            socket.send(JSON.stringify({
                name: name,
                ids: ids,
                action: {{ \App\Enums\EnumChat::CREATE_CHANNEL  }},
            }))
        }

        function scrollMessage() {
            $('#messages').animate({
                scrollTop: $('#messages').get(0).scrollHeight
            }, 500);
        }

        function MessageMe(name, created_at, avatar, text) {
            const avatar_user = avatar ? avatar : 'https://kiemtientuweb.com/ckfinder/userfiles/images/avatar-trang/avatar-trang-11.jpg';
            return `
                <div class="direct-chat-msg right">
                      <div class="direct-chat-infos clearfix">
                        <span class="direct-chat-name float-right">${name}</span>
                        <span class="direct-chat-timestamp float-left">${created_at}</span>
                      </div>
                      <img class="direct-chat-img" src="${avatar_user}" alt="${name}">
                      <div class="direct-chat-text">
                        ${text}
                      </div>
                </div>`
        }

        function MessageOther(name, created_at, avatar, text) {
            const avatar_user = avatar ? avatar : 'https://kiemtientuweb.com/ckfinder/userfiles/images/avatar-trang/avatar-trang-11.jpg';
            return `
                <div class="direct-chat-msg">
                      <div class="direct-chat-infos clearfix">
                        <span class="direct-chat-name float-left">${name}</span>
                        <span class="direct-chat-timestamp float-right">${created_at}</span>
                      </div>
                      <img class="direct-chat-img" src="${avatar_user}" alt="${name}">
                      <div class="direct-chat-text">
                        ${text}
                      </div>
                </div>`
        }
</script>
<script>
    $(function () {
        //Initialize Select2 Elements
        $('.select2').select2()
    });
</script>
@endsection('script')
