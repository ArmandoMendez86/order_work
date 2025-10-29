<?php
require_once __DIR__ . '/../../fpdf/fpdf.php';
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../models/WorkOrder.php';
include_once __DIR__ . '/../models/WorkOrderPhoto.php';

class PDF extends FPDF
{
    // Cabecera de página
    function Header()
    {
        // Ruta absoluta construida desde la ubicación de este archivo (WorkOrderPdfController.php)
        // __DIR__ . '/../../' apunta a la raíz del proyecto. Luego se añade 'assets/img/pride.jpg'
        $logo_path = __DIR__ . '/../../assets/img/pride.jpg';

        // 1. Logo (Ajusta la ruta si es necesario. Se asume que está en assets/img/pride.jpg)
        // Las coordenadas son: (ruta, X, Y, Ancho)
        $this->Image($logo_path, 10, 8, 30);

        // 2. Título (Movido ligeramente a la derecha para no chocar con el logo)
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(45); // Mueve el cursor a la derecha (45 mm desde el margen izquierdo)
        $this->Cell(70, 10, 'Work Order Report', 0, 0, 'C');

        // 3. Información de la empresa
        $this->SetFont('Arial', '', 10);
        $this->SetY(10);
        $this->SetX(150);
        $this->Cell(50, 5, 'Pride Enterprice Inc.', 0, 1, 'R');
        $this->SetX(150);
        $this->Cell(50, 5, '126 Ipswich Road, Boxford MA 01921', 0, 1, 'R');
        $this->SetX(150);
        $this->Cell(50, 5, '781-241-3543', 0, 1, 'R');

        // Salto de línea
        $this->Ln(15);
    }

    // Pie de página
    function Footer()
    {
        // Posición a 1.5 cm del final
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        // Número de página
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

class WorkOrderPdfController
{
    private $db;
    private $workOrderModel;
    private $workOrderPhotoModel;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->workOrderModel = new WorkOrder($this->db);
        $this->workOrderPhotoModel = new WorkOrderPhoto($this->db);
    }

    private function checkSession()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            // No enviamos JSON, ya que esta respuesta es para el navegador/descarga
            http_response_code(401);
            echo "No autorizado. Inicie sesión.";
            exit();
        }
    }

    // Método para obtener los nombres completos de los técnicos
    private function getInvolvedTechnicianNames($work_order_id)
    {
        $query = "SELECT u.full_name 
                  FROM workordertechnicians wot
                  JOIN users u ON wot.user_id = u.user_id
                  WHERE wot.work_order_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$work_order_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    public function generate($id)
    {
        $this->checkSession();

        $data = $this->workOrderModel->findById($id);

        if (!$data) {
            http_response_code(404);
            echo "Work Order not found.";
            return;
        }

        // Obtener datos adicionales
        $photos = $this->workOrderPhotoModel->getPhotosByWorkOrder($id);
        $photosBefore = array_filter($photos, fn($p) => $p['photo_type'] === 'before');
        $photosAfter = array_filter($photos, fn($p) => $p['photo_type'] === 'after');
        $involvedTechnicians = $this->getInvolvedTechnicianNames($id);

        // Inicializar PDF
        $pdf = new PDF();
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 20);

        // --- Título de la Orden ---
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Work Order #: ' . $data['work_order_number'], 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Service Date: ' . $data['service_date'], 0, 1, 'L');
        $pdf->Cell(0, 5, 'Status: ' . $data['status'], 0, 1, 'L');

        // Espacio de separación
        $pdf->Ln(7);

        // --- 1. Customer & General Details ---
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 7, '1. Customer & General Details (Admin)', 0, 1, 'L', true);
        $pdf->SetFont('Arial', '', 10);

        $pdf->Cell(60, 5, 'Customer Name:', 0, 0);
        $pdf->Cell(0, 5, $data['customer_name'], 0, 1);
        $pdf->Cell(60, 5, 'City:', 0, 0);
        $pdf->Cell(0, 5, $data['customer_city'], 0, 1);
        $pdf->Cell(60, 5, 'Phone:', 0, 0);
        $pdf->Cell(0, 5, $data['customer_phone'], 0, 1);
        $pdf->Cell(60, 5, 'Type:', 0, 0);
        $pdf->Cell(0, 5, $data['customer_type'], 0, 1);
        $pdf->Cell(60, 5, 'Category/Subcategory:', 0, 0);
        $pdf->Cell(0, 5, $data['category_name'] . ($data['subcategory_name'] ? ' / ' . $data['subcategory_name'] : ''), 0, 1);
        $pdf->Cell(60, 5, 'Responsible Technician:', 0, 0);
        $pdf->Cell(0, 5, $data['assign_to_email'], 0, 1);

        // Espacio de separación
        $pdf->Ln(7);

        // --- 2. Activity Description (Admin) ---
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 7, '2. Activity Description (Admin)', 0, 1, 'L', true);
        $pdf->SetFont('Arial', '', 10);
        // Borde cambiado de 1 a 0
        $pdf->MultiCell(0, 5, $data['activity_description'] ?? 'N/A', 0, 'L');

        // Espacio de separación
        $pdf->Ln(7);

        // --- 3. Technician Report ---
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 7, '3. Technician Report', 0, 1, 'L', true);
        $pdf->SetFont('Arial', '', 10);

        $pdf->Cell(60, 5, 'Start Date/Time:', 0, 0);
        $pdf->Cell(40, 5, $data['start_datetime'] ?? 'N/A', 0, 0);
        $pdf->Cell(60, 5, 'End Date/Time:', 0, 0);
        $pdf->Cell(0, 5, $data['end_datetime'] ?? 'N/A', 0, 1);

        $pdf->Cell(60, 5, 'Total Hours:', 0, 0);
        $pdf->Cell(40, 5, $data['total_hours'] ?? 'N/A', 0, 0);
        $pdf->Cell(60, 5, 'Estimated Duration:', 0, 0);
        $pdf->Cell(0, 5, $data['estimated_duration'] ?? 'N/A', 0, 1);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Materials Used:', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        // Borde cambiado de 1 a 0
        $pdf->MultiCell(0, 5, $data['materials_used'] ?? 'N/A', 0, 'L');

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Work Description:', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        // Borde cambiado de 1 a 0
        $pdf->MultiCell(0, 5, $data['work_description'] ?? 'N/A', 0, 'L');

        // Espacio de separación
        $pdf->Ln(7);

        // --- 4. Team & Signatures ---
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 7, '4. Team & Signatures', 0, 1, 'L', true);
        $pdf->SetFont('Arial', '', 10);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Involved Technicians (Team):', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 5, implode(', ', $involvedTechnicians) ?: 'None', 0, 'L');

        // Espacio de separación
        $pdf->Ln(7);

        // --- Signatures ---

        // FIRMA DEL TÉCNICO
        $y = $pdf->GetY();
        $pdf->Cell(95, 5, 'Technician Signature (' . ($data['tech_signature_name_print'] ?? 'N/A') . ')', 0, 0, 'L');

        // FIRMA DEL MANAGER
        $pdf->Cell(95, 5, 'Manager/Cashier Signature (' . ($data['manager_signature_name_print'] ?? 'N/A') . ')', 0, 1, 'L');

        // Manejo de firmas Base64 (FPDF requiere un truco para Base64)
        $signature_width = 80;
        $signature_height = 30;

        // Firma del Técnico
        $temp_file_tech = null;
        if (!empty($data['tech_signature_base64'])) {
            $png_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['tech_signature_base64']));
            $temp_file_tech = __DIR__ . '/../../uploads/temp/tech_sig_' . $id . '.png';
            file_put_contents($temp_file_tech, $png_data);
            $pdf->Image($temp_file_tech, 15, $y + 5, $signature_width, $signature_height, 'PNG');
        } else {
            $pdf->SetXY(15, $y + 10);
            $pdf->Cell(95, 10, 'No Signature Provided', 0, 0, 'L');
        }

        // Firma del Manager
        $temp_file_manager = null;
        if (!empty($data['manager_signature_base64'])) {
            $png_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['manager_signature_base64']));
            $temp_file_manager = __DIR__ . '/../../uploads/temp/manager_sig_' . $id . '.png';
            file_put_contents($temp_file_manager, $png_data);
            $pdf->Image($temp_file_manager, 15 + 95, $y + 5, $signature_width, $signature_height, 'PNG');
        } else {
            $pdf->SetXY(15 + 95, $y + 10);
            $pdf->Cell(95, 10, 'No Signature Provided', 0, 0, 'L');
        }

        // Asegurar que el cursor esté después de las firmas
        $pdf->SetY($y + $signature_height + 5);

        // Espacio de separación antes del salto de página
        $pdf->Ln(7);

        // --- INICIO DE LA SECCIÓN DE FOTOS EN LA SEGUNDA PÁGINA ---
        $pdf->AddPage(); // **FUERZA UN SALTO DE PÁGINA AQUÍ**

        // --- 5. Photos Before & After ---

        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 7, '5. Photos Before & After', 0, 1, 'L', true);
        $pdf->SetFont('Arial', '', 10);

        $pdf->Ln(2);
        $pdf->Cell(0, 5, 'Photos Before:', 0, 1);
        $this->addPhotosToPdf($pdf, $photosBefore);

        $pdf->Ln(5);
        $pdf->Cell(0, 5, 'Photos After:', 0, 1);
        $this->addPhotosToPdf($pdf, $photosAfter);

        // Limpiar archivos temporales de firmas
        if (isset($temp_file_tech) && file_exists($temp_file_tech)) unlink($temp_file_tech);
        if (isset($temp_file_manager) && file_exists($temp_file_manager)) unlink($temp_file_manager);


        // Salida
        $pdf->Output('I', 'WO-' . $data['work_order_number'] . '.pdf');
    }

    // Función auxiliar para agregar fotos al PDF
    private function addPhotosToPdf($pdf, $photos)
    {
        $current_x = 10;
        $max_width = 180;
        $image_size = 50;
        $margin = 5;
        $images_per_row = floor($max_width / ($image_size + $margin));
        $counter = 0;

        foreach ($photos as $photo) {
            $file_path = __DIR__ . '/../../' . $photo['file_path'];

            if (file_exists($file_path)) {

                // Mover a la siguiente página si no hay espacio
                if ($pdf->GetY() + $image_size + 10 > $pdf->GetPageHeight() - 20) {
                    $pdf->AddPage();
                    $current_x = 10;
                    $counter = 0;
                }

                if ($counter % $images_per_row == 0 && $counter != 0) {
                    $pdf->Ln($image_size + $margin);
                    $current_x = 10;
                }

                $pdf->Image($file_path, $current_x, $pdf->GetY(), $image_size);
                $current_x += $image_size + $margin;
                $counter++;
            }
        }
        $pdf->Ln($image_size + $margin);
    }
}
