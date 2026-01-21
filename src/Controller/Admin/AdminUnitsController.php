<?php

namespace App\Controller\Admin;

use App\Service\UnitsManager;
use App\Service\CsrfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/admin/units', name: 'admin_units_')]
class AdminUnitsController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(UnitsManager $unitsManager)
    {
        $units = $unitsManager->getAllUnits();
        $data = array_map([$unitsManager, 'serializeUnit'], $units);

        return $this->json($data);
    }
   
    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(CsrfService $csrfService, Request $request, UnitsManager $unitsManager)
    {
        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['name']) || empty($data['name'])) {
            return $this->json(['error' => 'Name is required'], Response::HTTP_BAD_REQUEST);
        }

        $unit = $unitsManager->createUnit($data['name'], $data['symbol'] ?? null);

        return $this->json([
            'status' => 'Unit created successfully',
            'unit' => $unitsManager->serializeUnit($unit)
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['PUT'])]
    public function edit(int $id, CsrfService $csrfService, Request $request, UnitsManager $unitsManager)
    {
        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $unit = $unitsManager->getUnitById($id);
        if (!$unit) {
            return $this->json(['error' => 'Unit not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        $unit = $unitsManager->updateUnit($unit, $data['name'] ?? null, $data['symbol'] ?? null);

        return $this->json([
            'status' => 'Unit updated successfully',
            'unit' => $unitsManager->serializeUnit($unit)
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id, CsrfService $csrfService, Request $request, UnitsManager $unitsManager)
    {
        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $unit = $unitsManager->getUnitById($id);
        if (!$unit) {
            return $this->json(['error' => 'Unit not found'], Response::HTTP_NOT_FOUND);
        }

        $unitsManager->deleteUnit($unit);

        return $this->json(['status' => 'Unit deleted successfully']);
    }
}
