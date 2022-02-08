<?php

namespace App\Controller;

use App\Entity\Folder;
use Aws\S3\S3Client;
use Doctrine\Persistence\ManagerRegistry;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FolderController extends AbstractController {

    #[Route('/folders', name: 'list_folders')]
    public function getAll(Request $request, ManagerRegistry $doctrine, SerializerInterface $serializer): Response {
        $repository = $doctrine->getRepository(Folder::class);
        $data = $repository->findAll();

        $json = $serializer->serialize($data, 'json');
        return JsonResponse::fromJsonString($json, Response::HTTP_OK);
    }

    #[Route('/folder/get/{id}', name: 'get_folder_content', requirements: ['id' => '.+'])]
    public function getFolderContent(ManagerRegistry $doctrine, SerializerInterface $serializer, int $id = 1): Response {
        $repository = $doctrine->getRepository(Folder::class);
        $folder = $repository->find($id);

        if(is_null($folder)){
            return new Response('not found', Response::HTTP_NOT_FOUND);
        }

        $client = new S3Client([
            'version' => 'latest',
            'region' => $this->getParameter('app.bucket.region'),
            'credentials' => [
                'key' => $this->getParameter('app.bucket.key'),
                'secret' => $this->getParameter('app.bucket.secret'),
            ],
            'http' => [
                'verify' => false
            ]
        ]);

        $objects = $client->listObjects([
            'Bucket' => $this->getParameter('app.bucket.name'),
        ]);


        foreach ($folder->getContent() as $r) {
            $cmd = $client->getCommand('GetObject', [
                'Bucket' => $this->getParameter('app.bucket.name'),
                'Key' => $r->getFilename()
            ]);

            $request = $client->createPresignedRequest($cmd, '+25 minutes');
            $presignedUrl = (string)$request->getUri();

            $r->url = $presignedUrl;
        }

        $json = $serializer->serialize($folder, 'json');
        //return new JsonResponse(json_encode($json), Response::HTTP_OK);

        return JsonResponse::fromJsonString($json, Response::HTTP_OK);
    }


    /**
     * @param int $id
     * @param ManagerRegistry $doctrine
     * @param SerializerInterface $serializer
     * @return Response
     */
    #[Route('/folder/delete/{id}', name: 'delete_folder', methods: 'DELETE')]
    public function delete(int $id, ManagerRegistry $doctrine, SerializerInterface $serializer): Response{
        $em = $doctrine->getManager();
        $folder = $em->find(Folder::class, $id);

        $em->remove($folder);
        $em->flush();
        return new Response('ok', Response::HTTP_OK);
    }


    #[Route('/folders/structure', name: 'get_structure')]
    public function getStructure(ManagerRegistry $doctrine, SerializerInterface $serializer){
        $repository = $doctrine->getRepository(Folder::class);
        $root = $repository->find(1);

        $data = [
            'key' => $root->getId(),
            'name' => $root->getName(),
            'children' => [],
            'data' => $root->getContent()
        ];

        /** @var Folder $children */
        foreach ($root->getChildrenFolder() as $children){
            $data['children'][] = [
                'key' => $children->getParentFolder()->getId() . '_' . $children->getId(),
                'name' => $children->getName(),
                'children' => [],
                'data' => $children->getContent()
            ];
        }

        $json = $serializer->serialize($data, 'json');
        return JsonResponse::fromJsonString($json, Response::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param ManagerRegistry $doctrine
     * @param SerializerInterface $serializer
     * @return Response
     */
    #[Route('/folder/create', name: 'create_folder')]
    public function create(Request $request, ManagerRegistry $doctrine, SerializerInterface $serializer): Response{
        $em = $doctrine->getManager();
        $post = $request->toArray();
        $parent = $em->find(Folder::class, $post['id_parent']);

        $folder = new Folder();
        $folder->setName($post['name']);
        $folder->setParentFolder($parent);

        $em->persist($folder);
        $em->flush();

        $json = $serializer->serialize($folder, 'json');
        return new Response($json, Response::HTTP_OK);
    }


    /**
     * @param String $path
     * @param ManagerRegistry $doctrine
     * @param SerializerInterface $serializer
     */
    #[Route('/folder/resolve_path/{fullpath}', name: 'get_by_path', requirements: ['fullpath' => '.+'])]
    public function findByPath(String $fullpath, ManagerRegistry $doctrine, SerializerInterface $serializer): Response{
        $repository = $doctrine->getRepository(Folder::class);
        $parent = $repository->find(1);
        $data = NULL;
        $folders = explode('/', $fullpath);

        /** @var Folder $f */
        foreach ($folders as $f){
            $data = $repository->findOneBy([
                'name' => $f,
                'parent_folder' => $parent
            ]);
            $parent = $data;
        }

        $json = $serializer->serialize($data, 'json');
        return JsonResponse::fromJsonString($json, Response::HTTP_OK);
    }
}
