<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Update - DENR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        .update-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .status-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 15px;
        }
        .status-icon.success {
            background: #d4edda;
            color: #155724;
        }
        .status-icon.error {
            background: #f8d7da;
            color: #721c24;
        }
        .status-icon.pending {
            background: #fff3cd;
            color: #856404;
        }
        .step-item {
            padding: 15px;
            margin: 10px 0;
            border-left: 3px solid #28a745;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .step-item.completed {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .step-item.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .btn-update {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
        }
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="update-container">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0">
                    <i class="fas fa-database"></i>
                    Database Update - Tree Species & Verification Documents
                </h2>
            </div>
            <div class="card-body">
                <div id="status-message"></div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Important:</strong> This script will update your database schema by:
                    <ul class="mb-0 mt-2">
                        <li>Creating a <code>tree_species</code> table with 20 Philippine tree species</li>
                        <li>Adding <code>verification_document</code> column to <code>plantations</code> table</li>
                    </ul>
                </div>

                <div id="update-steps"></div>

                <div class="text-center mt-4">
                    <button class="btn btn-success btn-lg btn-update" onclick="runUpdate()">
                        <i class="fas fa-play"></i> Run Database Update
                    </button>
                </div>

                <hr class="my-4">

                <h5><i class="fas fa-terminal"></i> Manual Installation (Alternative)</h5>
                <p>If the automatic update doesn't work, run these SQL commands manually:</p>
                
                <div class="accordion" id="sqlAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sqlCode">
                                <i class="fas fa-code"></i> &nbsp; View SQL Code
                            </button>
                        </h2>
                        <div id="sqlCode" class="accordion-collapse collapse" data-bs-parent="#sqlAccordion">
                            <div class="accordion-body">
                                <div class="code-block">
                                    <small>Copy and paste this into phpMyAdmin:</small>
                                    <pre class="mb-0" style="white-space: pre-wrap;">-- Run this in your denrdb database

-- Create tree species table
CREATE TABLE IF NOT EXISTS `tree_species` (
  `species_id` int(11) NOT NULL AUTO_INCREMENT,
  `species_name` varchar(100) NOT NULL,
  `scientific_name` varchar(150) DEFAULT NULL,
  `common_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`species_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert tree species
INSERT INTO `tree_species` (`species_name`, `scientific_name`, `common_name`) VALUES
('Narra', 'Pterocarpus indicus', 'Philippine Mahogany'),
('Molave', 'Vitex parviflora', 'Molave'),
('Acacia', 'Acacia mangium', 'Acacia'),
('Mahogany', 'Swietenia macrophylla', 'Mahogany'),
('Ipil-ipil', 'Leucaena leucocephala', 'Ipil-ipil'),
('Gmelina', 'Gmelina arborea', 'Gmelina'),
('Bamboo', 'Bambusa vulgaris', 'Common Bamboo'),
('Yakal', 'Shorea astylosa', 'Yakal'),
('Kamagong', 'Diospyros blancoi', 'Velvet Apple'),
('Apitong', 'Dipterocarpus grandiflorus', 'Apitong'),
('Lauan', 'Shorea contorta', 'White Lauan'),
('Teak', 'Tectona grandis', 'Teak'),
('Mangium', 'Acacia mangium', 'Black Wattle'),
('Rubber Tree', 'Hevea brasiliensis', 'Rubber Tree'),
('Falcata', 'Paraserianthes falcataria', 'Falcata'),
('Agoho', 'Casuarina equisetifolia', 'Beach She-oak'),
('Mango', 'Mangifera indica', 'Mango Tree'),
('Coconut', 'Cocos nucifera', 'Coconut Palm'),
('Durian', 'Durio zibethinus', 'Durian'),
('Rambutan', 'Nephelium lappaceum', 'Rambutan');

-- Add verification document column
ALTER TABLE `plantations` 
ADD COLUMN IF NOT EXISTS `verification_document` varchar(255) DEFAULT NULL AFTER `longitude`;</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showStatus(icon, message, type) {
            const statusDiv = document.getElementById('status-message');
            statusDiv.innerHTML = `
                <div class="status-icon ${type}">
                    <i class="fas ${icon}"></i>
                </div>
                <h4 class="text-center">${message}</h4>
            `;
        }

        function addStep(message, status = 'pending') {
            const stepsDiv = document.getElementById('update-steps');
            const stepId = 'step-' + Date.now();
            const step = document.createElement('div');
            step.id = stepId;
            step.className = `step-item ${status}`;
            step.innerHTML = `
                <i class="fas ${status === 'completed' ? 'fa-check-circle text-success' : status === 'error' ? 'fa-times-circle text-danger' : 'fa-spinner fa-spin text-warning'}"></i>
                &nbsp; ${message}
            `;
            stepsDiv.appendChild(step);
            return stepId;
        }

        function updateStep(stepId, message, status) {
            const step = document.getElementById(stepId);
            if (step) {
                step.className = `step-item ${status}`;
                step.innerHTML = `
                    <i class="fas ${status === 'completed' ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'}"></i>
                    &nbsp; ${message}
                `;
            }
        }

        async function runUpdate() {
            const btn = event.target.closest('button');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            
            document.getElementById('update-steps').innerHTML = '';
            showStatus('fa-spinner fa-spin', 'Running database update...', 'pending');

            try {
                const response = await fetch('run_tree_species_update.php', {
                    method: 'POST'
                });
                const result = await response.json();

                if (result.success) {
                    showStatus('fa-check-circle', 'Database updated successfully!', 'success');
                    
                    result.steps.forEach(step => {
                        addStep(step.message, step.status ? 'completed' : 'error');
                    });

                    setTimeout(() => {
                        window.location.href = '../modules/landowner/plantations/plantations.php';
                    }, 2000);
                } else {
                    showStatus('fa-times-circle', 'Update failed!', 'error');
                    addStep(result.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-redo"></i> Retry Update';
                }
            } catch (error) {
                showStatus('fa-times-circle', 'Connection error!', 'error');
                addStep('Could not connect to server: ' + error.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> Retry Update';
            }
        }
    </script>
</body>
</html>
