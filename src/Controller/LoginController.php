<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController {

    /**
     * @Route("/login", name="login")
     * @param AuthenticationUtils $authenticationUtils
     * @return Response
     */
    public function index(AuthenticationUtils $authenticationUtils): Response {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return new Response();
    }

    /**
     * @Route("/token/verify", name="check_token")
     * @param Request $request
     * @param ManagerRegistry $doctrine
     * @param SerializerInterface $serializer
     * @return Response
     */
    public function checkToken(Request $request, ManagerRegistry $doctrine, SerializerInterface $serializer): Response {
        $repository = $doctrine->getRepository(User::class);
        $fullToken = preg_split('/(\.\$2y\$)/', $request->toArray()['token']);

        $token = '$2y$' . array_pop($fullToken);
        $id = array_pop($fullToken);

        $user = $repository->find($id);
        if (password_verify($user->getUsername(), $token)) {
            $data = [
                'userData' => [
                    'id' => $user->getId(),
                    'username' => $user->getUserIdentifier(),
                    'role' => $user->getRole()->getId(),
                    'creation' => $user->getRole()->getCanCreate(),
                    'deletion' => $user->getRole()->getCanDelete()
                ],
                'token' => $user->getId() . '.' . password_hash($user->getUsername(), PASSWORD_BCRYPT)
            ];
        } else{
            $data = [];
        }

        $json = $serializer->serialize($data, 'json');
        return new Response($json, Response::HTTP_OK);
    }
}
