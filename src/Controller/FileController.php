<?php

namespace App\Controller;

use App\Entity\Folder;
use App\Entity\Resource;
use App\Entity\Tag;
use App\Service\EfreiboxClient;
use Doctrine\Common\Collections\ArrayCollection;
use Aws\S3\S3Client;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FileController extends AbstractController {

    private S3Client $client;

    public function __construct(EfreiboxClient $efreiboxClient) {
        $this->client = $efreiboxClient::createS3Client();
    }

    #[Route('/files', name: 'list_files')]
    public function getAll(SerializerInterface $serializer): Response {

        $objects = $this->client->listObjects([
            'Bucket' => $this->getParameter('app.bucket.name'),
        ]);

        $data = [];
        foreach ($objects['Contents'] as $obj) {
            $cmd = $this->client->getCommand('GetObject', [
                'Bucket' => $this->getParameter('app.bucket.name'),
                'Key' => $obj['Key']
            ]);

            $request = $this->client->createPresignedRequest($cmd, '+25 minutes');
            $presignedUrl = (string)$request->getUri();

            $obj['url'] = $presignedUrl;
            $data[] = $obj;
        }

        $json = $serializer->serialize($data, 'json');
        //return new JsonResponse(json_encode($json), Response::HTTP_OK);

        return JsonResponse::fromJsonString($json, Response::HTTP_OK);
    }


    #[Route('/file/get/{id}', name: 'get_image', methods: ['GET'])]
    public function getOne(int $id, SerializerInterface $serializer): Response {
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'ams3',
            'endpoint' => $_ENV['DO_ENDPOINT'],
            'credentials' => [
                'key' => $_ENV['DO_KEY_ACCESS'],
                'secret' => $_ENV['DO_SECRET'],
            ],
            'http' => [
                'verify' => false
            ]
        ]);

        $cmd = $client->getCommand('GetObject', [
            'Bucket' => 'niwa',
            'Key' => 'Japanese mythology A to Z.pdf'
        ]);

        $request = $client->createPresignedRequest($cmd, '+25 minutes');
        $presignedUrl = (string)$request->getUri();

        $file = $presignedUrl;

        return new Response($presignedUrl, Response::HTTP_OK);
    }


    #[Route('/image/file/{id}', name: 'get_raw')]
    public function serveImage(int $id): mixed {
        $image = $this->getDoctrine()->getRepository(Image::class)->find($id);
        $file = 'uploads/' . $image->getFilename();

        return new BinaryFileResponse($file);
    }

    /**
     * @param Request $request
     * @param SerializerInterface $serializer
     * @return Response
     */
    #[Route('/image/create', name: 'create_image')]
    public function create(Request $request, SerializerInterface $serializer): Response {
        $em = $this->getDoctrine()->getManager();
        $artist_repository = $this->getDoctrine()->getRepository(Artist::class);

        $params = $request->toArray();
        $filename = $params['filename'];
        $extension = $params['extension'];
        $url = $params['url'];
        $source = $params['source'];
        $user = $params['artist'];

        $artist = $artist_repository->findOneBy(['idAccount' => $user['id']]);
        if (!isset($artist)) {
            $artist = new Artist();
            $artist->setAccount($user['account']);
            $artist->setDescription('');
            $artist->setIdAccount($user['id']);
            $artist->setName($user['name']);
            $artist->setUrl('https://www.pixiv.net/en/users/' . $user['id']);
        }

        $image = new Image();
        $image->setArtist($artist);
        $image->setUrl($url);
        $image->setFilename($filename . $extension);
        $image->setSource($source);

        $em->persist($image);
        $em->flush();

        return new Response('ok', Response::HTTP_OK);
    }


    #[Route('/image/update', name: 'update_image')]
    public function save(Request $request, SerializerInterface $serializer): Response {
        $em = $this->getDoctrine()->getManager();
        $tag_repository = $this->getDoctrine()->getRepository(Tag::class);

        $params = $request->toArray();
        $id = $params['id'];
        $tags = $params['tags'];

        $image = $em->find(Image::class, $id);
        $image->setTags(new ArrayCollection());

        foreach ($tags as $key => $value) {
            //Tag not in current tags, so we add it
            $t = $tag_repository->find($value['id']);
            if (!$image->getTags()->contains($value)) {
                $image->addTag($t);
            }
        }

        $em->persist($image);
        $em->flush();

        $json = $serializer->serialize($tags, 'json');
        return new Response($json, Response::HTTP_OK);
    }

}
