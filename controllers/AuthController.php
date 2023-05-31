<?php
class Auth
{
    protected $db;
    protected $errors;

    public function __construct()
    {
        $this->errors = "";
    }

    // Check the session validity
    public function checkSession()
    {
        $this->loginID = $_COOKIE['auth'];
        
        DB::connect();
        $query = DB::select('logs', '*', "loginID = '$this->loginID' AND loggedout = 0")->fetchAll();
        DB::close();
    
        if ($query) {
            $this->currentLog = $query[0];
            return $this->currentLog;
        } else {
            return false;
        }
    }

    public function login($email, $password)
    {
        DB::connect();
        $this->email = strtolower(trim(DB::sanitize($email)));
        $this->password = DB::sanitize($password);
        DB::close();

        
        
        DB::connect();
        $loginQuery = DB::select('users', '*', "email = '$this->email'")->fetchAll()[0];
        //return gettype($loginQuery->fetchAll());
        DB::close();
        // Check if user exists
        if($loginQuery){
            // Check if password is correct
            if($loginQuery['password'] == md5($this->password)){
                
                $this->loginID = md5(sha1($this->email).sha1($this->password).sha1(time()));
                setcookie("auth", $this->loginID, time() + (86400 * 365), "/");

                $this->ip = getDevice()['ip'];
                $this->os = getDevice()['os'];
                $this->browser = getDevice()['browser'];

                $time = date_create()->format('Y-m-d H:i:s');
                $data = [
                    'loginID' => $this->loginID,
                    'email' => $this->email,
                    'ip' => $this->ip,
                    'browser' => $this->browser,
                    'os' => $this->os,
                    'loggedinat' => $time
                ];
                
                DB::connect();
                $insertedLog = DB::insert('logs', $data);
                DB::close();

                if($insertedLog){
                    if(!empty($_GET['back'])){
                        header("Location:".$_GET['back']);
                    }
                    else{
                        header("Location:".home());
                    }
                }
                else{
                    $this->errors = "Internal Server Error";
                }
            }
            else{
                $this->errors = "Password Doesn't Match";
            }
        }
        else{
            $this->errors = "User Not Found";
        }
        return ['error'=>true, 'errorMsg'=>$this->errors];
    }

    // Check if email exists
    public function check($email, $role)
    {
        DB::connect();
        $result = DB::select('users', '*', "email = '$email' and role = '$role'")->fetchAll();
        DB::close();
        return count($result);
    }
    
    public function getUser($email)
    {
        DB::connect();
        $getUser = DB::select('users', '*', "email = 'DB::sanitize($email)'")->fetchAll();
        DB::close();
        if($getUser) return $getUser;
        else return false;
    }

    public function getUsers()
    {
        DB::connect();
        $users = DB::select('users', '*')->fetchAll();
        DB::close();
        if($users) return $users;
        else return false;
    }


    public function register($name, $email, $phone, $password, $role)
    {
        // Sanitize fields
        DB::connect();
        $this->name = trim(DB::sanitize($name));
        $this->email = strtolower(trim(DB::sanitize($email)));
        $this->phone = trim(DB::sanitize($phone));
        $this->passwordWithoutMD5 =  DB::sanitize($password);
        $this->role = trim(DB::sanitize($role));    
        DB::close();
        
        // fields array
        $fields = [
        'name' => [
            'value' => $this->name,
            'rules' => [
                [
                    'type' => 'required',
                    'message' => "Name can't be empty",
                ],
                [
                    'type' => 'minLength',
                    'message' => "Name can't be less than 6 characters",
                    'minLength' => 6,
                ]
            ]
        ],
        'email' => [
            'value' => $this->email,
            'rules' => [
                [
                    'type' => 'required',
                    'message' => "Email can't be empty",
                ],
                [
                    'type' => 'email',
                    'message' => 'Email is invalid',
                ],
                [
                    'type' => 'custom',
                    'message' => 'Email already in use',
                    'validate' => function () {
                        return !($this->check($this->email,$this->role));
                    },
                ],
            ],
            ],
            'phone' => [
                'value' => $this->phone,
                'rules' => [
                    [
                        'type' => 'required',
                        'message' => "Phone can't be empty",
                    ],
                    [
                        'type' => 'phone',
                        'message' => "Invalid Phone",
                    ]
                ]
            ],
            'password' => [
                'value' => $this->passwordWithoutMD5,
                'rules' => [
                    [
                        'type' => 'required',
                        'message' => "Password can't be empty",
                    ],
                    [
                        'type' => 'password',
                        'message' => "Invalid Password",
                    ]
                ]
            ],
        ];

        // Call the Validator::validate function
        $validate = Validator::validate($fields);
        if($validate['error']){
            return ['error' => $validate['error'], 'errorMsgs' => $validate['errorMsgs']];
        }else{

            $this->password = md5($this->passwordWithoutMD5);

            $data = array(
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'phone' => $this->phone,
                'role' => $this->role
            );
            
            
            DB::connect();
            $createUser = DB::insert('users', $data);
            DB::close();

            if($createUser){
                $this->error = false;
                $this->errorMsgs['createUser'] = '';
            }else{
                $this->error = true;
                $this->errorMsgs['createUser'] = 'User account creation failed';
            }

            if($this->error){
                return ['error' => $this->error, 'errorMsgs' => $this->errorMsgs];
            }else{
                $userLogin = new Auth();
                $userLogin->login($this->email, $this->passwordWithoutMD5);
            }
        }
        
    }


    public function edit($data){
        DB::connect();
        $this->name = trim(DB::sanitize($data['name']));
        $this->email = trim(DB::sanitize($data['email']));
        $this->phone = trim(DB::sanitize($data['phone']));
        $this->password = DB::sanitize($data['password']);
        $this->role = trim(DB::sanitize($data['role']));
        $this->status = trim(DB::sanitize($data['status']));
        DB::close();
        
        
        $fields = [
            'name' => [
                'value' => $this->name,
                'rules' => [
                    [
                        'type' => 'required',
                        'message' => "Name can't be empty",
                    ],
                    [
                        'type' => 'minLength',
                        'message' => "Name can't be less than 6 characters",
                        'minLength' => 6,
                    ]
                ]
            ],
            'email' => [
                'value' => $this->email,
                'rules' => [
                    [
                        'type' => 'required',
                        'message' => "Email can't be empty",
                    ],
                    [
                        'type' => 'email',
                        'message' => 'Email is invalid',
                    ],
                    [
                        'type' => 'custom',
                        'message' => 'Invalid User',
                        'validate' => function () {
                            return ($this->check($this->email,$this->role));
                        },
                    ],
                ],
                ],
                'phone' => [
                    'value' => $this->phone,
                    'rules' => [
                        [
                            'type' => 'required',
                            'message' => "Phone can't be empty",
                        ],
                        [
                            'type' => 'phone',
                            'message' => "Invalid Phone",
                        ]
                    ]
                ],
            ];
        
            // Call the validateFields function
            $validate = Validator::validate($fields);

        if($validate['error']){
            return ['error' => $validate['error'], 'errorMsgs' => $validate['errorMsgs']];
        }else{
            $this->password = md5($data['password']);
            
            $data = array(
                'name' => $this->name,
                'password' => $this->password,
                'phone' => $this->phone,
                'role' => $this->role,
                'status' => $this->status
            );
            
            DB::connect();
            $updateUser = DB::update('users', $data, "email = '$this->email'");
            DB::close();

            if($updateUser){
                $this->error = false;
                $this->errorMsgs['updateUser'] = '';
            }else{
                $this->error = true;
                $this->errorMsgs['updateUser'] = 'User account Updation failed ';
            }

            if($this->error){
                return ['error' => $this->error, 'errorMsgs' => $this->errorMsgs];
            }else{
                return [
                    'email' => $this->email,
                    'name' => $this->name,
                    'phone' => $this->phone,
                    'password' => $this->password,
                    'role' => $this->role
                ];
            }
        }
    }

    public function delete($email) {

        if(!$this->getUser($email)) {
            return [
                'error' => true,
                'errorMsg' => 'User not found'
            ];
        }
        
            $data = array(
                'status' => 1
            );
            
            DB::connect();
            $deleteUser = DB::update('users', $data, "email = '$email'");
            DB::close();

        if($deleteUser){
            return [
                'error' => false,
                'errorMsg' => ''
            ];
        }else{
            return [
                'error' => true,
                'errorMsg' => 'Failed to delete user '
            ];
        }
    }

    // Logout Function
    public function logout(){
        $loginID = App::getSession()['loginID'];
        $time = date_create()->format('Y-m-d H:i:s');
        
        
            $data = array(
                'loggedout' => 1,
                'loggedoutat' => $time
            );
            
            DB::connect();
            $updateLog = DB::update('logs', $data, "loginID = '$loginID'");
            DB::close();
            
        if($updateLog){
            setcookie("auth", "", time()-(60*60*24*7), "/");
            unset($_COOKIE["auth"]);
            header("Location:".home()."login?loggedout=true");
        }
    }

} 

