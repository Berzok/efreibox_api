<?php

namespace App\Controller;

use App\Entity\Folder;
use App\Entity\Resource;
use App\Service\EfreiboxClient;
use Aws\S3\S3Client;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ResourceController extends AbstractController {

    private S3Client $client;

    public function __construct(EfreiboxClient $efreiboxClient) {
        $this->client = $efreiboxClient::createS3Client();
    }

    /**
     * @param Request $request
     * @param ManagerRegistry $doctrine
     * @param SerializerInterface $serializer
     * @return Response
     */
    #[Route('/resource/upload', name: 'upload_resource')]
    public function upload(Request $request, ManagerRegistry $doctrine, SerializerInterface $serializer): Response {
        $em = $doctrine->getManager();
        $files = $request->files->get('files');
        $data = [];

        /* @var $f UploadedFile */
        foreach($files as $f){
            try{
                $key = str_replace('_', ' ', $f->getClientOriginalName());
                $folder = $em->find(Folder::class, $request->get('folder'));

                $resource = new Resource();
                $resource->setFolder($folder);
                $resource->setName($key);
                $resource->setFilename($key);

                $this->client->putObject([
                    'Bucket' => $this->getParameter('app.bucket.name'),
                    'Key' => $resource->getFilename(), //The Key (filename, it seems))
                    'Body' => $f->getContent(), //The contents of the file
                    'ACL' => 'private',
                    'ContentType' => $f->getMimeType(),
                    'Metadata' => array(
                        'x-amz-meta-my-key' => 'your-value'
                    )
                ]);

                $em->persist($resource);
                $em->flush();
            } catch (Exception $e){
                var_dump($e);
            }

            $data[] = $f;
        }

        $json = $serializer->serialize($data, 'json');
        return new Response($json, Response::HTTP_OK);
    }

    /**
     * @param int $id
     * @param ManagerRegistry $doctrine
     * @param SerializerInterface $serializer
     * @return Response
     */
    #[Route('/resource/delete/{id}', name: 'delete_resource')]
    public function delete(int $id, ManagerRegistry $doctrine, SerializerInterface $serializer): Response {
        $em = $doctrine->getManager();
        $resource = $em->find(Resource::class, $id);

        try {
            $this->client->deleteObject([
                'Bucket' => $this->getParameter('app.bucket.name'),
                'Key' => $resource->getFilename(),
            ]);

            $em->remove($resource);
            $em->flush();
        } catch (Exception $e) {
            var_dump($e);
        }

        $json = $serializer->serialize('ok', 'json');
        return new Response($json, Response::HTTP_OK);
    }
}
