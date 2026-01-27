# Create Symfony-compatible file structure with Twig templates
import os

# Create the necessary directories structure
directories = [
    "templates",
    "templates/admin",
    "public/css", 
    "public/js",
    "src/Controller"
]

# Create directories
for directory in directories:
    os.makedirs(directory, exist_ok=True)
    print(f"Created directory: {directory}")

print("\n✓ Symfony folder structure created successfully")