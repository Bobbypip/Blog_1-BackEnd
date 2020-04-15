<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Post;
use App\Helpers\JwtAuth;

class PostController extends Controller
{
    public function __construct(){
        //En este construcutor se declaran a que metodos de la clase no se les aplicara el Middleware
        $this->middleware('api.auth', ['except' => [
            'index',
            'show',
            'getImage',
            'getPostsByCategory',
            'getPostsByUser'
            ]]);
    }

    //all devuelve todos los elementos de la tabala
    public function index(){
        $posts = Post::all()->load('category'); //load me permite sacar un modelo adjunto en el json, en este caso todos los datos de la categoria de la entrada 

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'posts' => $posts
        ], 200);
    }

    //find devuelve un elemento de la tabla, una sugerencia es buscarlo por el id
    public function show($id){
        $post = Post::find($id)->load('category')
                               ->load('user');
        if(is_object($post)){
            $data = [
                'code' => 200,
                'status' => 'success',
                'posts' => $post
            ];
        }else{
            $data = [
                'code' => 200,
                'status' => 'success',
                'message' => 'La entrada no existe'
            ];
        }

        return response()->json($data, $data['code']);
    }

    public function store(Request $request){
        // Recoger datos por POST
        $json = $request->input('json', null);
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){
            //Conseguir usuario identificado
            $user = $this->getIdentity($request);

            //Validar los datos
            $validate = \Validator::make($params_array, [
                'title' => 'required',
                'content' => 'required',
                'category_id' => 'required',
                'image' => 'required'
            ]);

            if($validate->fails()){
                $data = [
                    'code' => 400,
                    'status' => 'error',
                    'message' => 'No se ha guardado el post, faltan datos'
                ];
            }else{
                //Guardar el articulo
                $post = new Post();
                $post->user_id = $user->sub;
                $post->category_id = $params->category_id;
                $post->title = $params->title;
                $post->content = $params->content;
                $post->image = $params->image;
                $post->save();

                $data = [
                    'code' => 200,
                    'status' => 'success',
                    'post' => $post
                ];

            }
            
        }else{
            $data = [
                'code' => 400,
                'status' => 'error',
                'message' => 'Envia los datos correctamente'
            ];
        }

        // Devolver la respuesta
        return response()->json($data, $data['code']);
    }

    public function update($id, Request $request){
        // Recoger los datos por post
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);

        //Conseguir usuario identificado
        $user = $this->getIdentity($request);

        //Datos para devolver
        $data = array(
            'code' => 400,
            'status' => 'error',
            'message' => 'Datos enviados incorrectos'
        );

        if(!empty($params_array)){
            //Validar los datos
            $validate = \Validator::make($params_array, [
                'title' => 'required',
                'content' => 'required',
                'category_id' => 'required',
            ]);

            if($validate->fails()){
                $data['errors'] = $validate->errors();
                return response()->json($data, $data['code']);//Es recomendable solo tener un punto de salida del metodo (1 solo return) esta es otra forma
            }

            $params_array['user_id'] = $user->sub;

            // Eliminar lo que no queremos actualizar
            unset($params_array['id']);
            unset($params_array['user_id']);
            unset($params_array['created_at']);
            unset($params_array['user']);

            //Conseguir usuario identificado
            $user = $this->getIdentity($request);

            //Actualizar el registro en concreto
            $post = Post::where('id', $id)
                        ->where('user_id', $user->sub)
                        ->update($params_array);

            //Devolver algo
            $data = array(
                'code' => 200,
                'status' => 'success',
                'post' => $post,
            );
        }

        return response()->json($data, $data['code']);
    }

    public function destroy($id, Request $request){
        //Conseguir usuario identificado
        $user = $this->getIdentity($request);

        // Conseguir el registro
        $post = Post::where('id', $id)
                    ->where('user_id', $user->sub)
                    ->first();

        if(!empty($post)){
            // Borrarlo
            $post->delete();

            // Devolver algo
            $data = [
                'code' => 200,
                'status' => 'success',
                'post' => $post
            ];
        }else{
            $data = [
                'code' => 400,
                'status' => 'error',
                'message' => 'El post no existe'
            ];
        }
        
        return response()->json($data, $data['code']);
    }

    private function getIdentity($request){
        $jwtAuth = new JwtAuth();
        $token = $request->header('Authorization', null);
        $user = $jwtAuth->checkToken($token, true);

        return $user;
    }

    public function upload(Request $request){
        //Recoger la imagen de la peticion
        $image = $request->file('file0');

        //validar la imagen
        $validate = \Validator::make($request->all(), [
            'file0' => 'required|image|mimes:jpg,jpeg,png,gif'
        ]);

        //Guardar la imagen
        if(!$image /*|| $validate->fails()*/){
            $data = [
                'code' => 400,
                'status' => 'error',
                'message' => 'Error al subir la imagen'
            ];
        }else{
            $image_name = time().$image->getClientOriginalName();

            \Storage::disk('images')->put($image_name, \File::get($image));
            //Guardar en el disco   ->con el nombre  , lo que se recogio.

            $data = [
                'code' => 200,
                'status' => 'success',
                'image' => $image_name
            ];
        }
        //Devolver datos
        return response()->json($data, $data['code']);
    }

    public function getImage($filename){
        //Comprobar si existe el fichero
        $isset = \Storage::disk('images')->exists($filename);//metodo exists de Storage devolvera true si existe el archivo, si no existe devolvera false

        if($isset){
            //Conseguir la imagen
            $file = \Storage::disk('images')->get($filename);//metodo get aplicado al Storage para obtener la imagen deseada

            //Devolver la imagen
            return new Response($file, 200);

        }else{
            $data = [
                'code' => 400,
                'status' => 'error',
                'message' => 'La imagen no existe'
            ];
        }

        return response()->json($data, $data['code']);
    }

    public function getPostsByCategory($id){
        $posts = Post::where('category_id', $id)->get();

        $data = [
            'code' => 200,
            'status' => 'success',
            'posts' => $posts
        ];

        return response()->json($data, $data['code']);
    }

    public function getPostsByUser($id){
        $posts = Post::where('user_id', $id)->get();

        $data = [
            'code' => 200,
            'status' => 'success',
            'posts' => $posts
        ];

        return response()->json($data, $data['code']);
    }


    
}
