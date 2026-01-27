# Create a comprehensive file listing to show the complete structure
import os
import glob

def list_files_recursively(directory, prefix=""):
    """List all files and directories recursively with tree-like structure"""
    items = []
    
    if not os.path.exists(directory):
        return [f"{prefix}📁 {directory}/ (Directory not found)"]
    
    try:
        # Get all items in the directory
        all_items = os.listdir(directory)
        all_items.sort()
        
        # Separate directories and files
        dirs = [item for item in all_items if os.path.isdir(os.path.join(directory, item))]
        files = [item for item in all_items if os.path.isfile(os.path.join(directory, item))]
        
        # Add directories first
        for i, dir_name in enumerate(dirs):
            is_last_dir = i == len(dirs) - 1 and len(files) == 0
            connector = "└── " if is_last_dir else "├── "
            extension = "    " if is_last_dir else "│   "
            
            items.append(f"{prefix}{connector}📁 {dir_name}/")
            
            # Recursively list subdirectory contents
            sub_items = list_files_recursively(
                os.path.join(directory, dir_name), 
                prefix + extension
            )
            items.extend(sub_items)
        
        # Add files
        for i, file_name in enumerate(files):
            is_last = i == len(files) - 1
            connector = "└── " if is_last else "├── "
            
            # Get file size
            try:
                file_path = os.path.join(directory, file_name)
                file_size = os.path.getsize(file_path)
                size_str = f" ({file_size:,} bytes)" if file_size > 0 else ""
            except:
                size_str = ""
            
            items.append(f"{prefix}{connector}📄 {file_name}{size_str}")
            
    except PermissionError:
        items.append(f"{prefix}❌ Permission denied")
    except Exception as e:
        items.append(f"{prefix}❌ Error: {str(e)}")
    
    return items

print("🗂️  COMPLETE SYMFONY PROJECT STRUCTURE")
print("=" * 50)

# List the complete file structure
structure = list_files_recursively(".")
for item in structure[:50]:  # Limit output to first 50 items
    print(item)

if len(structure) > 50:
    print(f"... and {len(structure) - 50} more items")

print(f"\n📊 SUMMARY:")
print(f"├── Total items created: {len(structure)}")
print(f"├── Templates: {len([x for x in structure if '.twig' in x])}")
print(f"├── Controllers: {len([x for x in structure if '.php' in x])}")
print(f"├── CSS files: {len([x for x in structure if '.css' in x])}")
print(f"├── JavaScript files: {len([x for x in structure if '.js' in x])}")
print(f"└── Documentation: {len([x for x in structure if '.md' in x])}")

print(f"\n✅ SYMFONY SNEAT DASHBOARD FILES CREATED SUCCESSFULLY!")
print("\n🚀 Next Steps:")
print("1. Copy all files to your Symfony project directory")
print("2. Install required dependencies: composer require symfony/twig-bundle symfony/asset")
print("3. Configure routes in config/routes.yaml")  
print("4. Access your dashboard at /admin")
print("5. Customize colors and styling in public/css/sneat-admin.css")
print("\n📖 Check README.md for detailed installation instructions!")