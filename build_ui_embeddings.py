#!/usr/bin/env python3
"""
Simple script to build neural embeddings for UI directory
"""

import json
import os
from pathlib import Path
import subprocess

def get_embedding(text):
    """Get embedding from Ollama using curl"""
    cmd = [
        'curl', '-s', 'http://localhost:11434/api/embeddings',
        '-d', json.dumps({"model": "nomic-embed-text", "prompt": text})
    ]
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode == 0:
        response = json.loads(result.stdout)
        return response.get("embedding", [])
    return []

def extract_ui_chunks():
    """Extract code chunks from UI directory"""
    chunks = []
    ui_dir = Path("ui")
    
    # Define extensions to process
    extensions = ['.js', '.vue', '.ts']
    
    # Walk through UI directory
    for ext in extensions:
        for file_path in ui_dir.rglob(f"*{ext}"):
            # Skip node_modules and other unwanted directories
            if 'node_modules' in str(file_path) or 'playwright-report' in str(file_path):
                continue
            
            try:
                content = file_path.read_text(encoding='utf-8', errors='ignore')
                # Create a chunk for each file
                chunks.append({
                    "file": str(file_path),
                    "type": ext[1:],  # Remove dot
                    "name": file_path.stem,
                    "content": content[:1000],  # First 1000 chars for context
                    "full_path": str(file_path.absolute())
                })
            except Exception as e:
                print(f"Error reading {file_path}: {e}")
    
    return chunks

def main():
    print("🔍 Extracting UI code chunks...")
    chunks = extract_ui_chunks()
    print(f"📦 Found {len(chunks)} files to process")
    
    print("\n🧠 Generating embeddings...")
    embeddings_data = {
        "chunks": [],
        "total": len(chunks)
    }
    
    for i, chunk in enumerate(chunks):
        if i % 10 == 0:
            print(f"  Processing {i+1}/{len(chunks)}...")
        
        # Create text for embedding
        text = f"{chunk['type']} file: {chunk['name']}\nPath: {chunk['file']}\n{chunk['content']}"
        
        # Get embedding
        embedding = get_embedding(text)
        
        if embedding:
            embeddings_data["chunks"].append({
                "chunk": chunk,
                "embedding": embedding
            })
    
    # Save to file
    with open("UI_NEURAL_INDEX.json", "w") as f:
        json.dump(embeddings_data, f, indent=2)
    
    print(f"\n✅ Saved {len(embeddings_data['chunks'])} embeddings to UI_NEURAL_INDEX.json")

if __name__ == "__main__":
    main()