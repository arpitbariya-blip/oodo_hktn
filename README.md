.
|   allocation.html
|   assets.html
|   audits.html
|   bookings.html
|   dashboard.html
|   index.html
|   login.html
|   logs.html
|   maintenance.html
|   org-setup.html
|   README.md
|   reports.html
|   
+---assets
|   \---js
|           auth.js
|           
\---backend
    |   .htaccess
    |   index.php
    |   schema.sql
    |   seed.sql
    |   
    +---config
    |       db.php
    |       
    +---controllers
    |       AuthController.php
    |       CategoryController.php
    |       DashboardController.php
    |       DepartmentController.php
    |       EmployeeController.php
    |       
    +---middleware
    |       AuthMiddleware.php
    |       RoleMiddleware.php
    |       
    +---models
    |       UserModel.php
    |       
    +---routes
    |       api.php
    |       
    \---utils
            Response.php