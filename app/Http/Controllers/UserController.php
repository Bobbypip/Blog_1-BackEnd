<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\User;

class UserController extends Controller
{
    public function pruebas(Request $request){
        return "Accion de pruebas de USER-CONTROLLER";
    }

    public function register(Request $request){

        // Recoger los datos del usuario
        $json = $request->input('json', null);
        //Decodificar el jason para pasarlo a un objeto de PHP
        $params = json_decode($json);
        //Decodificar el jason para pasarlo a un array de PHP
        $params_array = json_decode($json, true);

        if(!empty($params) && !empty($params_array)){
            //Limpiar datos
            $params_array = array_map('trim', $params_array);
            
            // Validar datos
            // Se usa la barra \ antes de Validator porque no se tiene el namespace en uso.
            $validate = \Validator::make($params_array, [
                'name'      => 'required|alpha',
                'surname'   => 'required|alpha',
                'email'     => 'required|email|unique:users',//unique|Compobar si el usuario existe (duplicado)
                'password'  => 'required',
            ]);

            if($validate->fails()){
                // Validacion fallida
                $data = array(
                    'status'    => 'error',
                    'code'      =>  404,
                    'message'   => 'El usuario no se ha creado',
                    'errors'    => $validate->errors()
                );
            }else{
                //Validacion correcta

                //Cifrar la contrasena
                //$pwd = password_hash($params->password, PASSWORD_BCRYPT, ['cost' => 4]);
                $pwd = hash('sha256', $params->password);

                //Crear el usuario
                $user = new User();
                
                // Crear el usuario
                $user = new User();
                $user->name = $params_array['name'];
                $user->surname = $params_array['surname'];
                $user->email = $params_array['email'];
                $user->password = $pwd;
                $user->role = 'ROLE_USER';

                //Guardar el usuario->save() es de ORM
                $user->save();

                $data = array(
                    'status'    => 'success',
                    'code'      =>  200,
                    'message'   => 'El usuario se ha creado',
                    'user'      => $user
                );
            }
        }else{
            $data = array(
                'status'    => 'error',
                'code'      =>  404,
                'message'   => 'Los datos enviados no son correctos',
            );
        }
        return response()->json($data, $data['code']);  
    }

    public function login(Request $request){

        $jwtAuth = new \JwtAuth();

        //Recibir datos por POST
        $json = $request->input('json', null);
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        //Validar datos
        $validate = \Validator::make($params_array, [
            'name'      => 'required|alpha',
            'password'  => 'required',
        ]);

        if($validate->fails()){
            // Validacion fallida
            $signup = array(
                'status'    => 'error',
                'code'      =>  404,
                'message'   => 'El usuario no se ha podido identificar',
                'errors'    => $validate->errors()
            );
        }else{

            //Cifrar la password
            $pwd = hash('sha256', $params->password);

            //Devolver token o datos
            $signup = $jwtAuth->signup($params->email, $pwd);

            if(!empty($params->gettoken)){
                $signup = $jwtAuth->signup($params->email, $pwd, true);
            }
        }

        return response()->json($signup, 200);
    }

    public function update(Request $request){

        //Comprobar si el usuario esta identificado
        $token = $request->header('Authorization');
        $jwtAuth = new \JwtAuth();
        $checkToken = $jwtAuth->checkToken($token);

        //Recoger los datos por post
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);

        if($checkToken && !empty($params_array)){

            //Sacar usuario identificado
            $user = $jwtAuth->checkToken($token, true);

            //Validar datos
            $validate = \Validator::make($params_array, [
                'name' => 'required|alpha',
                'surname' => 'required|alpha',
                'email' => 'required|email|unique:users'.$user->sub
            ]);

            //Quitar los campos que no quiero actualizar
            unset($params_array['id']);
            unset($params_array['role']);
            unset($params_array['created_at']);
            unset($params_array['remember_token']);

            //Actualizar usuario en bbdd
            $user_update = User::where('id',$user->sub)->update($params_array); // Si es correcto devolvera un 1 o un true

            //Devolver array con resultado
            $data = array(
                'code' => 200,
                'status' => 'success',
                'user' => $user,
                'changes' => $params_array
            );

        }else{

            $data = array(
                'code' => 400,
                'status' => 'error',
                'message' => 'El usuario no esta identificado'
            );
        }

        return response()->json($data, $data['code']);
    }

    public function upload(Request $request){
        //Recoger datos de la peticion
        $image = $request->file('file0');

        //Validacion de imagen
        $validate = \Validator::make($request->all(), [
            'file0' => 'required|image|mimes:jpg,jpeg,png,gif'
        ]);

        //Guardar imagen
        if(!$image || $validate->fails()){
            $data = array(
                'code' => 400,
                'status' => 'error',
                'iamge' => 'Error al subir imagen'
            );

        }else{
            $image_name = time().$image->getClientOriginalName();
            \Storage::disk('users')->put($image_name, \File::get($image));

            $data = array(
                'code' => 200,
                'status' => 'success',
                'iamge' => $image_name
            );
        }

        return response()->json($data, $data['code']);
    }

    public function getImage($filename){
        $isset = \Storage::disk('users')->exists($filename);
        if($isset){
            $file = \Storage::disk('users')->get($filename);
        return new Response($file, 200);
        }else{
            $data = array(
                'code' => 400,
                'status' => 'error',
                'iamge' => 'La imagen no existe'
            );
            return response()->json($data, $data['code']);
        }
    }

    public function detail($id){
        $user = User::find($id);

        if(is_object($user)){
            $data = array(
                'code' => 200,
                'status' => 'success',
                'user'=> $user
            );
        }else{
            $data = array(
                'code' => 404,
                'status' => 'error',
                'message'=> 'El usuario no existe'
            );
        }

        return response()->json($data, $data['code']);
    }
}
