<?php

namespace App\Http\Controllers;

use App\Enums\EnumChat;
use App\Models\Conversation;
use App\Models\ConversationUser;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;

class SocketController extends Controller implements MessageComponentInterface
{

    protected SplObjectStorage $clients;

    public function __construct()
    {
        $this->clients = new SplObjectStorage;

    }

    function onOpen(ConnectionInterface $conn)
    {
        $user = $this->user($conn);
        if ($user) {
            $user->update(['connection_id' => $conn->resourceId, 'online_status' => 'Online']);

            $userData = $user->first();

            $data = [
                'id'     => $userData->id,
                'name'   => $userData->name,
                'status' => $userData->online_status,
                'avatar' => $userData->avatar,
            ];

            foreach ($this->clients as $client) {
                if ($client->resourceId != $conn->resourceId) {
                    $client->send(json_encode($data));
                }
            }
        }
    }

    private function user(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $querystring = $conn->httpRequest->getUri()->getQuery();
        parse_str($querystring, $queryArray);
        $token = $queryArray['token'] ?? false;
        if (!$token) {
            return false;
        }
        $user = User::where('token', $token);
        if (!$user->first()){
            return false;
        }
        return $user;
    }

    function onClose(ConnectionInterface $conn)
    {
        $user = $this->user($conn);
        if ($user) {
            $user->update(['connection_id' => 0, 'online_status' => 'Offline']);
            $userData = $user->first();

            $data = [
                'id'     => $userData->id,
                'name'   => $userData->name,
                'status' => $userData->online_status,
                'avatar' => $userData->avatar,
            ];

            foreach ($this->clients as $client) {
                if ($client->resourceId != $conn->resourceId) {
                    $client->send(json_encode($data));
                }
            }
        }
    }

    function onError(ConnectionInterface $conn, Exception $e)
    {
        // TODO: Implement onError() method.
    }

    function onMessage(ConnectionInterface $conn, $msg)
    {
        $user = $this->user($conn);
        if ($user) {
            $data   = json_decode($msg);
            $action = $data->action ?? false;

            // user with token
            $userData = $user->first();

            if ($action == EnumChat::MESSAGE) {

                // validate
                $validator = Validator::make((array)$data, [
                    'conversation_id' => 'required|exists:conversations,id',
                    'text'            => 'required|string',
                ]);

                try {
                    $validatedData = $validator->validated();

                    $conversationId = $validatedData['conversation_id'];
                    $text           = $validatedData['text'];

                } catch (\Illuminate\Validation\ValidationException $e) {
                    foreach ($this->clients as $client) {
                        if ($client->resourceId == $conn->resourceId) {
                            $client->send(json_encode([
                                'errors' => $e->errors(),
                                'type'   => EnumChat::ERROR
                            ]));
                        }
                    }
                }

                // get all user
                $users = User::query()
                    ->join('conversation_users', function ($join) use ($conversationId) {
                        $join->on('users.id', '=', 'conversation_users.user_id')
                            ->on('conversation_users.conversation_id', DB::raw($conversationId));
                    })
                    ->select('users.*');

                $connectionUserInConversation = $users->pluck('connection_id');
                $userInConversation           = $users->pluck('id');

                if ($userInConversation->contains($userData->id)) { // user contains in group

                    // save message

                    try {
                        Message::query()->create([
                            'conversation_id' => $conversationId,
                            'sender_id'       => $userData->id,
                            'text'            => $data->text,
                        ]);
                    } catch (Exception $exception) {
                        report($exception);
                    }


                    foreach ($this->clients as $client) {
                        // send to user in group
                        if ($connectionUserInConversation->contains($client->resourceId)) {
                            $client->send(json_encode([
                                'sender_id'       => $userData->id,
                                'name'            => $userData->name,
                                'avatar'          => $userData->avatar,
                                'text'            => $data->text,
                                'conversation_id' => $conversationId,
                                'created_at'      => Carbon::now(),
                                'type'            => EnumChat::MESSAGE
                            ]));
                        }
                    }
                }
            }

            if ($action == EnumChat::CHANNEL) {

                $channels = Conversation::query()->select('conversations.*')
                    ->join('conversation_users', function ($join) use ($userData) {
                        $join->on('conversation_users.conversation_id', 'conversations.id')
                            ->on('conversation_users.user_id', DB::raw($userData->id));
                    })
                    ->latest()
                    ->get();

                $data = [
                    'channels' => $channels,
                    'type'     => EnumChat::CHANNEL
                ];
                foreach ($this->clients as $client) {
                    if ($client->resourceId == $conn->resourceId) {
                        $client->send(json_encode($data));
                    }
                }
            }

            if ($action == EnumChat::LOAD_MESSAGE) {
                $conversationId = $data->conversation_id ?? false;
                if ($conversationId) {
                    $messages = Message::query()->select([
                        'messages.*',
                        'users.name',
                        'users.avatar',
                        'users.online_status',
                    ])
                        ->join('users', function ($join) use ($conversationId) {
                            $join->on('messages.conversation_id', DB::raw($conversationId))
                                ->on('users.id', 'messages.sender_id');
                        });


                    $data = [
                        'messages' => $messages->get(),
                        'type'     => EnumChat::LOAD_MESSAGE
                    ];
                    foreach ($this->clients as $client) {
                        if ($client->resourceId == $conn->resourceId) {
                            $client->send(json_encode($data));
                        }
                    }

                }
            }

            if ($action == EnumChat::GET_USER) {
                $users = User::query()->get();
                $data  = [
                    'users' => $users,
                    'type'  => EnumChat::GET_USER
                ];
                foreach ($this->clients as $client) {
                    if ($client->resourceId == $conn->resourceId) {
                        $client->send(json_encode($data));
                    }
                }
            }

            if ($action == EnumChat::CREATE_CHANNEL) {
                // validate
                $validator = Validator::make((array)$data, [
                    'name' => 'required|string',
                    'ids'  => 'required',
                ]);

                try {
                    $validatedData = $validator->validated();

                    $name = $validatedData['name'];
                    $ids  = $validatedData['ids'];

                } catch (\Illuminate\Validation\ValidationException $e) {
                    foreach ($this->clients as $client) {
                        if ($client->resourceId == $conn->resourceId) {
                            $client->send(json_encode([
                                'errors' => $e->errors(),
                                'type'   => EnumChat::ERROR
                            ]));
                        }
                    }
                }


                $conversation = Conversation::query()->create([
                    'name' => $name,
                    'user_init_id' => $userData->id,
                ]);
                $conversationId = $conversation->id;

                // add user create to channel
                ConversationUser::create([
                    'user_id' => $userData->id,
                    'conversation_id' => $conversationId,
                ]);

                foreach ($ids  as $id) {
                    ConversationUser::create([
                        'user_id' => $id,
                        'conversation_id' => $conversationId,
                    ]);
                }
                $users = User::query()->whereIn('id', $ids);
                $connectionUserInConversation = $users->pluck('connection_id');

                foreach ($this->clients as $client) {
                    if ($connectionUserInConversation->contains($client->resourceId) || $client->resourceId == $conn->resourceId) {
                        $client->send(json_encode([
                            'name' => $name,
                            'user_init_id' => $userData->id,
                            'conversation_id' => $conversationId,
                            'type'   => EnumChat::CREATE_CHANNEL
                        ]));
                    }
                }
            }
        }
    }
}
