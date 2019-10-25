<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use BackendBundle\Entity\User;
use AppBundle\Form\RegisterType;
use AppBundle\Form\UserType;

class UserController extends Controller {

    private $session;

    public function __construct() {
        $this->session = new Session();
    }

    public function loginAction(Request $request) {
        //Para no entrar con el url a login desde home
        if (is_object($this->getUser())) {
            return $this->redirect('home');
        }

        $authenticationUtils = $this->get('security.authentication_utils');
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('AppBundle:User:login.html.twig', array(
                    'last_username' => $lastUsername,
                    'error' => $error
        ));
    }

    public function registerAction(Request $request) {
        //Para no entrar con el url a register desde el home
        if (is_object($this->getUser())) {
            return $this->redirect('home');
        }


        $user = new User();
        $form = $this->createForm(RegisterType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                //$user_repo = $em->getRepository("BackendBundle:User");

                $query = $em->createQuery('SELECT u FROM BackendBundle:User u WHERE u.email = :email OR u.nick = :nick ')
                        ->setParameter('email', $form->get("email")->getData())
                        ->setParameter('nick', $form->get("nick")->getData());

                $user_isset = $query->getResult();

                //Si user_isset es igual a cero, entonces crea el usuario
                if (count($user_isset) == 0) {

                    $factory = $this->get("security.encoder_factory");
                    $encoder = $factory->getEncoder($user);
                    //Pasa el valor por post que llega del formulario
                    $password = $encoder->encodePassword($form->get("password")->getData(), $user->getSalt());

                    $user->setPassword($password);
                    $user->setRole("ROLE_USER");
                    $user->setImage(null);

                    $em->persist($user);
                    $flush = $em->flush();

                    if ($flush == null) {
                        $status = "Te has registrado correctamente";

                        $this->session->getFlashBag()->add("status", $status);
                        return $this->redirect("login");
                    } else {
                        $status = "No te has registrado correctamente";
                    }
                } else {
                    $status = "El usuario ya existe";
                }
            } else {
                $status = "¡No te has registrado correctamente!";
            }

            $this->session->getFlashBag()->add("status", $status);
        }


        return $this->render('AppBundle:User:register.html.twig', array(
                    "form" => $form->createView()
        ));
    }

    public function nickTestAction(Request $request) {
        $nick = $request->get("nick");

        $em = $this->getDoctrine()->getManager();
        $user_repo = $em->getRepository("BackendBundle:User");
        $user_isset = $user_repo->findOneBy(array("nick" => $nick));

        $result = "used";
        if (count($user_isset) >= 1 && is_object($user_isset)) {
            $result = "used";
        } else {
            $result = "unused";
        }

        return new Response($result);
    }

    public function editUserAction(Request $request) {

        //se cacha al usuario
        $user = $this->getUser();
        $user_image = $user->getImage();
        //se pasa los datos al usuario en los espacios del formulario que se creo en UserType.php
        $form = $this->createForm(UserType::class, $user);
        //brindar esa relación de info del user
        $form->handleRequest($request);
        //validar si es valido el usuario
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                //query para comprar si el usuario existe
                $query = $em->createQuery('SELECT u FROM BackendBundle:User u WHERE u.email = :email OR u.nick = :nick ')
                        ->setParameter('email', $form->get("email")->getData())
                        ->setParameter('nick', $form->get("nick")->getData());

                $user_isset = $query->getResult();
               

                //Validación para actualizar los datos del usuario
                if (($user->getEmail() == $user_isset[0]->getEmail() && $user->getNick() == $user_isset[0]->getNick()) || count($user_isset) == 0) {
                    
                    //upload file
                    $file = $form["image"]->getData();
                    //Sino está vacio el file, se agrega la imagen
                    if(!empty($file) && $file != null){
                        $ext = $file->guessExtension();
                        if($ext == 'jpg' || $ext == 'jpeg' || $ext == 'png' || $ext == 'gif'){
                            $file_name = $user->getId().time().'.'.$ext;
                            $file->move("uploads/users", $file_name);
                            
                            $user->setImage($file_name);
                        }
                    }else{
                        //obtener imagen por defecto
                        $user->setImage($user_image);
                    }

                    $em->persist($user);
                    $flush = $em->flush();

                    if ($flush == null) {
                        $status = "Has modificado tus datos correctamente";
                    } else {
                        $status = "No has modificado tus datos correctamente";
                    }
                } else {
                    $status = "El usuario ya existe";
                }
            } else {
                $status = "¡No se han actualizado tus datos correctamente!";
            }

            $this->session->getFlashBag()->add("status", $status);
            
            return $this->redirect('my-data');
        }



        //Se renderiza el edit_user.html con el furmulario
        return $this->render('AppBundle:User:edit_user.html.twig', array(
                    "form" => $form->createView()
        ));
    }

    public function usersAction(Request $request){

        $em = $this->getDoctrine()->getManager();

        $dql = "SELECT u FROM BackendBundle:User u";
        $query = $em->createQuery($dql);

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
                $query, $request->query->getInt('page', '1'), 5
        );

        return $this->render('AppBundle:User:users.html.twig', array(
            'pagination' => $pagination
        ));
    }

}