#!/usr/bin/env python3
"""
Find Redundant Components in Codebase
Analyzes neural embeddings to find semantically similar functions that could be merged
"""

import json
import math
import os
import sys
from collections import defaultdict
from typing import List, Dict, Tuple

def cosine_similarity(vec1: List[float], vec2: List[float]) -> float:
    """Calculate cosine similarity between two vectors"""
    if not vec1 or not vec2 or len(vec1) != len(vec2):
        return 0.0
    
    dot_product = sum(a * b for a, b in zip(vec1, vec2))
    norm1 = math.sqrt(sum(a * a for a in vec1))
    norm2 = math.sqrt(sum(b * b for b in vec2))
    
    if norm1 == 0 or norm2 == 0:
        return 0.0
    
    return dot_product / (norm1 * norm2)

def find_similar_functions(index_path: str, similarity_threshold: float = 0.85) -> Dict[str, List[Tuple[str, float]]]:
    """Find functions with high semantic similarity"""
    
    # Load the neural index
    with open(index_path, 'r') as f:
        data = json.load(f)
    
    embeddings = data['embeddings']
    similar_groups = defaultdict(list)
    processed = set()
    
    print(f"Analyzing {len(embeddings)} embeddings for redundancies...")
    print(f"Similarity threshold: {similarity_threshold}")
    print("=" * 70)
    
    # Compare each function with others
    for i, emb1 in enumerate(embeddings):
        key1 = f"{emb1['file']}:{emb1['function']}"
        if key1 in processed:
            continue
            
        vec1 = emb1['embedding']
        similar_to_this = []
        
        for j, emb2 in enumerate(embeddings):
            if i >= j:  # Don't compare with self or already compared pairs
                continue
                
            key2 = f"{emb2['file']}:{emb2['function']}"
            if key2 in processed:
                continue
                
            # Skip if same file and similar name (likely overloads)
            if emb1['file'] == emb2['file'] and emb1['function'].split(':')[0] == emb2['function'].split(':')[0]:
                continue
                
            vec2 = emb2['embedding']
            similarity = cosine_similarity(vec1, vec2)
            
            if similarity >= similarity_threshold:
                similar_to_this.append((key2, similarity))
                processed.add(key2)
        
        if similar_to_this:
            similar_groups[key1] = similar_to_this
            processed.add(key1)
    
    return similar_groups

def analyze_redundancies(index_path: str):
    """Main analysis function"""
    
    # Different similarity thresholds for different types of redundancy
    high_similarity = find_similar_functions(index_path, 0.90)  # Very similar - likely duplicates
    medium_similarity = find_similar_functions(index_path, 0.85)  # Similar - could be merged
    
    print("\n🔴 CRITICAL REDUNDANCIES (>90% similarity - likely duplicates):")
    print("=" * 70)
    if high_similarity:
        for func, similar in high_similarity.items():
            parts = func.split(':', 1)  # Split only on first colon
            file1, name1 = parts[0], parts[1] if len(parts) > 1 else parts[0]
            print(f"\n📍 {name1}")
            print(f"   File: {file1}")
            print(f"   Similar functions:")
            for similar_func, score in similar:
                parts2 = similar_func.split(':', 1)  # Split only on first colon
                file2, name2 = parts2[0], parts2[1] if len(parts2) > 1 else parts2[0]
                print(f"   • {name2} ({file2}) - {score:.1%} match")
    else:
        print("   No critical redundancies found")
    
    print("\n🟡 POTENTIAL REDUNDANCIES (85-90% similarity - consider merging):")
    print("=" * 70)
    
    # Filter out items already in high_similarity
    medium_only = {k: v for k, v in medium_similarity.items() if k not in high_similarity}
    
    if medium_only:
        for func, similar in medium_only.items():
            parts = func.split(':', 1)  # Split only on first colon
            file1, name1 = parts[0], parts[1] if len(parts) > 1 else parts[0]
            print(f"\n📍 {name1}")
            print(f"   File: {file1}")
            print(f"   Similar functions:")
            for similar_func, score in similar:
                parts2 = similar_func.split(':', 1)  # Split only on first colon
                file2, name2 = parts2[0], parts2[1] if len(parts2) > 1 else parts2[0]
                print(f"   • {name2} ({file2}) - {score:.1%} match")
    else:
        print("   No additional potential redundancies found")
    
    # Group by common patterns
    print("\n📊 REDUNDANCY PATTERNS:")
    print("=" * 70)
    
    with open(index_path, 'r') as f:
        data = json.load(f)
    
    # Analyze naming patterns
    function_names = defaultdict(list)
    for emb in data['embeddings']:
        # Extract function name from signature
        func_name = emb['function'].split(':')[0] if ':' in emb['function'] else emb['function']
        
        # Group by base name (remove prefixes like get, set, handle, etc.)
        base_name = func_name
        for prefix in ['get', 'set', 'handle', 'use', 'fetch', 'update', 'validate', 'format']:
            if base_name.lower().startswith(prefix):
                base_name = base_name[len(prefix):]
                break
        
        if base_name:
            function_names[base_name.lower()].append(f"{emb['file']}:{func_name}")
    
    # Show groups with multiple similar functions
    print("\nFunctions with similar base names (potential for consolidation):")
    for base_name, functions in sorted(function_names.items()):
        if len(functions) > 2:  # Only show if 3+ similar functions
            print(f"\n• '{base_name}' pattern ({len(functions)} functions):")
            for func in functions[:5]:  # Show first 5
                file_path, func_name = func.split(':')
                print(f"  - {func_name} in {file_path}")
            if len(functions) > 5:
                print(f"  ... and {len(functions) - 5} more")

if __name__ == "__main__":
    index_path = "NEURAL_INDEX.json"
    
    if not os.path.exists(index_path):
        print(f"❌ {index_path} not found. Please run neural-embeddings-filtered.py first.")
        sys.exit(1)
    
    analyze_redundancies(index_path)