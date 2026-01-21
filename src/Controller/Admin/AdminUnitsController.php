<?php

namespace App\Controller\Admin;

use App\Entity\Units;
use App\Repository\UnitsRepository;
use App\Service\CsrfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route('/admin/units', name: 'admin_units_')]
class AdminUnitsController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(UnitsRepository $unitsRepository)
    {
        $units = $unitsRepository->findAll();
        $data = array_map(function($unit) {
            return [
                'id' => $unit->getId(),
                'name' => $unit->getName(),
                'symbol' => $unit->getSymbol()
            ];
        }, $units);

        return $this->json($data);
    }
   
    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(CsrfService $csrfService, Request $request, EntityManagerInterface $em){


        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['name']) || empty($data['name'])) {
            return $this->json(['error' => 'Name is required'], Response::HTTP_BAD_REQUEST);
        }

        $unit = new Units();
        $unit->setName($data['name']);
        if (isset($data['symbol'])) {
            $unit->setSymbol($data['symbol']);
        }

        $em->persist($unit);
        $em->flush();

        return $this->json([
            'status' => 'Unit created successfully',
            'unit' => [
                'id' => $unit->getId(),
                'name' => $unit->getName(),
                'symbol' => $unit->getSymbol()
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['PUT'])]
    public function edit(int $id, CsrfService $csrfService, Request $request, EntityManagerInterface $em)
    {
        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $unit = $em->getRepository(Units::class)->find($id);
        if (!$unit) {
            return $this->json(['error' => 'Unit not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name']) && !empty($data['name'])) {
            $unit->setName($data['name']);
        }
        if (isset($data['symbol'])) {
            $unit->setSymbol($data['symbol']);
        }

        $em->flush();

        return $this->json([
            'status' => 'Unit updated successfully',
            'unit' => [
                'id' => $unit->getId(),
                'name' => $unit->getName(),
                'symbol' => $unit->getSymbol()
            ]
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id, CsrfService $csrfService, Request $request, EntityManagerInterface $em)
    {
        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $unit = $em->getRepository(Units::class)->find($id);
        if (!$unit) {
            return $this->json(['error' => 'Unit not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($unit);
        $em->flush();

        return $this->json(['status' => 'Unit deleted successfully']);
    }
}
