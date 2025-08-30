#!/usr/bin/env python3
"""
Search UI embeddings for similar/redundant code
"""

import json
import sys
import subprocess
from pathlib import Path

def get_embedding(text):
    """Get embedding from Ollama"""
    cmd = [
        'curl', '-s', 'http://localhost:11434/api/embeddings',
        '-d', json.dumps({"model": "nomic-embed-text", "prompt": text})
    ]
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode == 0:
        response = json.loads(result.stdout)
        return response.get("embedding", [])
    return []

def cosine_similarity(vec1, vec2):
    """Calculate cosine similarity between two vectors"""
    if not vec1 or not vec2:
        return 0
    
    dot_product = sum(a * b for a, b in zip(vec1, vec2))
    magnitude1 = sum(a * a for a in vec1) ** 0.5
    magnitude2 = sum(b * b for b in vec2) ** 0.5
    
    if magnitude1 == 0 or magnitude2 == 0:
        return 0
    
    return dot_product / (magnitude1 * magnitude2)

def search_embeddings(query, top_n=10):
    """Search for similar code in embeddings"""
    # Load embeddings
    with open("UI_NEURAL_INDEX.json", "r") as f:
        data = json.load(f)
    
    # Get query embedding
    print(f"🔍 Getting embedding for query: '{query}'")
    query_embedding = get_embedding(query)
    
    if not query_embedding:
        print("❌ Failed to get query embedding")
        return []
    
    # Calculate similarities
    results = []
    for chunk_data in data["chunks"]:
        chunk = chunk_data["chunk"]
        embedding = chunk_data["embedding"]
        
        similarity = cosine_similarity(query_embedding, embedding)
        results.append({
            "file": chunk["file"],
            "type": chunk["type"],
            "name": chunk["name"],
            "similarity": similarity,
            "preview": chunk["content"][:200]
        })
    
    # Sort by similarity
    results.sort(key=lambda x: x["similarity"], reverse=True)
    
    return results[:top_n]

def find_duplicates(threshold=0.85):
    """Find potentially duplicate/redundant code"""
    # Load embeddings
    with open("UI_NEURAL_INDEX.json", "r") as f:
        data = json.load(f)
    
    duplicates = []
    chunks = data["chunks"]
    
    print(f"🔍 Comparing {len(chunks)} files for similarities (threshold: {threshold})...")
    
    for i in range(len(chunks)):
        for j in range(i + 1, len(chunks)):
            similarity = cosine_similarity(
                chunks[i]["embedding"],
                chunks[j]["embedding"]
            )
            
            if similarity >= threshold:
                duplicates.append({
                    "file1": chunks[i]["chunk"]["file"],
                    "file2": chunks[j]["chunk"]["file"],
                    "similarity": similarity
                })
    
    duplicates.sort(key=lambda x: x["similarity"], reverse=True)
    return duplicates

def main():
    if len(sys.argv) < 2:
        print("Usage:")
        print("  python3 search_ui_embeddings.py search <query>")
        print("  python3 search_ui_embeddings.py duplicates [threshold]")
        print("\nExamples:")
        print("  python3 search_ui_embeddings.py search 'wallet connection'")
        print("  python3 search_ui_embeddings.py search 'price calculation'")
        print("  python3 search_ui_embeddings.py duplicates 0.9")
        return
    
    command = sys.argv[1]
    
    if command == "search":
        if len(sys.argv) < 3:
            print("❌ Please provide a search query")
            return
        
        query = " ".join(sys.argv[2:])
        results = search_embeddings(query)
        
        print(f"\n🎯 Top {len(results)} results for '{query}':\n")
        for i, result in enumerate(results, 1):
            print(f"{i}. {result['file']} (similarity: {result['similarity']:.3f})")
            print(f"   Type: {result['type']}, Name: {result['name']}")
            print(f"   Preview: {result['preview'][:100]}...")
            print()
    
    elif command == "duplicates":
        threshold = float(sys.argv[2]) if len(sys.argv) > 2 else 0.85
        duplicates = find_duplicates(threshold)
        
        if duplicates:
            print(f"\n⚠️  Found {len(duplicates)} potential duplicates:\n")
            for dup in duplicates:
                print(f"📁 {dup['file1']}")
                print(f"📁 {dup['file2']}")
                print(f"   Similarity: {dup['similarity']:.3f}")
                print()
        else:
            print(f"✅ No duplicates found with threshold {threshold}")

if __name__ == "__main__":
    main()