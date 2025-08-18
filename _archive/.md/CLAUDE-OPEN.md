# Gemini CLI: Best Practices for Effective Collaboration

This document outlines best practices for working with Gemini CLI to ensure efficient and successful software development tasks.

## Project Context: Go API Alignment

This Go repository aims to functionally and syntactically match existing API implementations in PHP, Java, and Node.js, which are referenced in the `*_repomix-output.xml` files at the root of this repository.

The core task involves porting and aligning 13 specific API functions. It is crucial that these functions retain their original names and that their signatures (parameters and return types) are functionally and syntactically identical to the reference implementations to ensure a consistent developer experience.

**Important Directives:**
*   **Do NOT invent new functions.** Only implement functions that have a direct semantic match in the reference implementations.
*   **Do NOT consolidate function logic.** Each function should mirror its counterpart in the reference APIs.
*   **Focus on the 13 core functions.** Helper or utility functions are not included in this count.

The 13 core API functions to be implemented/aligned are:

| API                                         |
| :------------------------------------------ |
| **`C_CERTIFICATE Class`**                   |
| `SetData(data)`: Sets the data content of the certificate. |
| `GetData()`: Retrieves the data content of the certificate. |
| `GetJSONCertificate()`: Returns the certificate as a JSON string. |
| `GetCertificateSize()`: Returns the size of the certificate in bytes. |
| **`CEP_Account Class`**                     |
| `Open(address)`: Opens an account with the given address. |
| `UpdateAccount()`: Updates the account's Nonce by querying the network. |
| `SetNetwork(network)`: Sets the blockchain network. |
| `SetBlockchain(chain)`: Sets the blockchain address. |
| `Close()`: Closes the account and resets all fields. |
| `SignData(data, privateKey)`: Signs data using the account's private key. |
| `SubmitCertificate(pdata, privateKey)`: Submits a certificate to the blockchain. |
| `GetTransactionOutcome(TxID, timeoutSec)`: Polls for transaction outcome. |
| `GetTransactionbyID(TxID, Start, End)`: Searches for a transaction by its ID. |

The Java implementation (`java-enterprise-api_repomix-output.xml`) should be considered the primary reference for functional and syntactic alignment. All existing tests must pass upon completion of the alignment.

## Task Management

For complex or multi-step tasks, you can use the `save_memory` tool to track progress or break down work into manageable steps.

## File Handling and Reading

Understanding file content is crucial before making modifications.

1.  **Targeted Information Retrieval**:
    *   When searching for specific content, patterns, or definitions within a codebase, prefer using search tools like `search_file_content` or `glob`. This is more efficient than reading entire files.

2.  **Reading File Content**:
    *   **Small to Medium Files**: For files where full context is needed or that are not excessively large, the `read_file` tool can be used to retrieve the entire content.
    *   **Large File Strategy**:
        1.  **Assess Size**: Before reading a potentially large file, its size should be determined (e.g., using `ls -l` via the `run_shell_command` tool or by an initial `read_file` with a small `limit` to observe if content is truncated).
        2.  **Chunked Reading**: If a file is large (e.g., over a few thousand lines), it should be read in manageable chunks (e.g., 1000-2000 lines at a time) using the `offset` and `limit` parameters of the `read_file` tool. This ensures all content can be processed without issues.
    *   Always ensure that the file path provided to `read_file` is absolute.

## File Editing

Precision is key for successful file edits. The following strategies lead to reliable modifications:

1.  **Pre-Edit Read**: **Always** use the `read_file` tool to fetch the content of the file *immediately before* attempting any `replace` operation. This ensures modifications are based on the absolute latest version of the file.

2.  **Constructing `old_string` (The text to be replaced)**:
    *   **Exact Match**: The `old_string` must be an *exact* character-for-character match of the segment in the file you intend to replace. This includes all whitespace (spaces, tabs, newlines) and special characters.
    *   **No Read Artifacts**: Crucially, do *not* include any formatting artifacts from the `read_file` tool's output (e.g., `cat -n` style line numbers or display-only leading tabs) in the `old_string`. It must only contain the literal characters as they exist in the raw file.
    *   **Sufficient Context & Uniqueness**: Provide enough context (surrounding lines) in `old_string` to make it uniquely identifiable at the intended edit location. The "Anchor on a Known Good Line" strategy is preferred: `old_string` is a larger, unique block of text surrounding the change or insertion point. This is highly reliable.

3.  **Constructing `new_string` (The replacement text)**:
    *   **Exact Representation**: The `new_string` must accurately represent the desired state of the code, including correct indentation, whitespace, and newlines.
    *   **No Read Artifacts**: As with `old_string`, ensure `new_string` does *not* contain any `read_file` tool output artifacts.

4.  **Choosing the Right Editing Tool**:
    *   **`replace` Tool**: Suitable for a single, well-defined replacement in a file.
    *   **`replace` Tool with `expected_replacements`**: Preferred when multiple changes are needed within the same file. Edits are applied sequentially, with each subsequent edit operating on the result of the previous one. This tool is highly effective for complex modifications.

5.  **Verification**:
    *   The success confirmation from the `replace` tool (especially if `expected_replacements` is used and matches) is the primary indicator that the change was made.
    *   If further visual confirmation is needed, use the `read_file` tool with `offset` and `limit` parameters to view only the specific section of the file that was changed, rather than re-reading the entire file.

### Reliable Code Insertion with `replace` (with `expected_replacements`)

When inserting larger blocks of new code (e.g., multiple functions or methods) where a simple `old_string` might be fragile due to surrounding code, the following `replace` strategy can be more robust:

1.  **First Edit - Targeted Insertion Point**: For the primary code block you want to insert (e.g., new methods within a class), identify a short, unique, and stable line of code immediately *after* your desired insertion point. Use this stable line as the `old_string`.
    *   The `new_string` will consist of your new block of code, followed by a newline, and then the original `old_string` (the stable line you matched on).
    *   Example: If inserting methods into a class, the `old_string` might be the closing brace `}` of the class, or a comment that directly follows the class.

2.  **Second Edit (Optional) - Ancillary Code**: If there's another, smaller piece of related code to insert (e.g., a function call within an existing method, or an import statement), perform this as a separate, more straightforward edit within the same `replace` call (if using `expected_replacements` for multiple changes) or as a new `replace` call. This edit usually has a more clearly defined and less ambiguous `old_string`.

**Rationale**:
*   By anchoring the main insertion on a very stable, unique line *after* the insertion point and prepending the new code to it, you reduce the risk of `old_string` mismatches caused by subtle variations in the code *before* the insertion point.
*   Keeping ancillary edits separate allows them to succeed even if the main insertion point is complex, as they often target simpler, more reliable `old_string` patterns.
*   This approach leverages `replace`'s sequential application of changes effectively.

**Example Scenario**: Adding new methods to a class and a call to one of these new methods elsewhere.
*   **Edit 1**: Insert the new methods. `old_string` is the class's closing brace `}`. `new_string` is `\n    [new methods code]\n    }`.
*   **Edit 2**: Insert the call to a new method. `old_string` is `// existing line before call`. `new_string` is `// existing line before call\n    this.newMethodCall();`.

This method provides a balance between precise editing and handling larger code insertions reliably when direct `old_string` matches for the entire new block are problematic.

## Handling Large Files for Incremental Refactoring

When refactoring large files incrementally rather than rewriting them completely:

1. **Initial Exploration and Planning**:
   * Begin with targeted searches using `search_file_content` to locate specific patterns or sections within the file.
   * Use `run_shell_command` with `grep -n "pattern" file` to find line numbers for specific areas of interest.
   * Create a clear mental model of the file structure before proceeding with edits.

2. **Chunked Reading for Large Files**:
   * For files too large to read at once, use multiple `read_file` operations with different `offset` and `limit` parameters.
   * Read sequential chunks to build a complete understanding of the file.
   * Use `search_file_content` to pinpoint key sections, then read just those sections with targeted `offset` parameters.

3. **Finding Key Implementation Sections**:
   * Use `run_shell_command` with `grep -A N` (to show N lines after a match) or `grep -B N` (to show N lines before) to locate function or method implementations.
   * Example: `grep -n "function findTagBoundaries" -A 20 filename.js` to see the first 20 lines of a function.

4. **Pattern-Based Replacement Strategy**:
   * Identify common patterns that need to be replaced across the file.
   * Use the `run_shell_command` tool with `sed` for quick previews of potential replacements.
   * Example: `sed -n "s/oldPattern/newPattern/gp" filename.js` to preview changes without making them.

5. **Sequential Selective Edits**:
   * Target specific sections or patterns one at a time rather than attempting a complete rewrite.
   * Focus on clearest/simplest cases first to establish a pattern of successful edits.
   * Use `replace` for well-defined single changes within the file.

6. **Batch Similar Changes Together**:
   * Group similar types of changes (e.g., all references to a particular function or variable).
   * Use `run_shell_command` with `sed` to preview the scope of batch changes: `grep -n "pattern" filename.js | wc -l`
   * For systematic changes across a file, consider using `sed` through the `run_shell_command` tool: `sed -i "s/oldPattern/newPattern/g" filename.js`

7. **Incremental Verification**:
   * After each set of changes, verify the specific sections that were modified.
   * For critical components, read the surrounding context to ensure the changes integrate correctly.
   * Validate that each change maintains the file's structure and logic before proceeding to the next.

8. **Progress Tracking for Large Refactors**:
   * Use the `save_memory` tool to track which sections or patterns have been updated.
   * Create a checklist of all required changes and mark them off as they're completed.
   * Record any sections that require special attention or that couldn't be automatically refactored.

## General Interaction

Gemini CLI will directly apply proposed changes and modifications using the available tools, rather than describing them and asking you to implement them manually. This ensures a more efficient and direct workflow.

## Tool-Specific Guidelines: Phantom MCP

The Phantom Model Context Protocol (MCP) is configured to be invoked via `npx @aku11i/phantom`. This means that when you interact with the Phantom MCP through Cursor, the system will execute the `phantom` command using the `npx` utility, which is available in your Nix development environment.

### How Phantom is Invoked:
- The `mcp.json` configuration for "Phantom" uses `"command": "npx"` and `"args": ["-y", "@aku11i/phantom", "mcp", "serve"]`.
- This setup ensures that the `phantom` package is resolved and executed correctly, leveraging your Node.js environment provided by Nix.

### Using Phantom through Cursor:
- You can now use any Cursor functionality that relies on the Phantom MCP directly.
- Ensure that your Nix development environment is active (e.g., by running `nix develop` or being in a shell where your `flake.nix` is loaded) when attempting to use Phantom-related features.

If you encounter any issues with Phantom, verify:
1. That your `flake.nix` includes `pkgs.nodejs` in your `devShells`.
2. That the `mcp.json` configuration for "Phantom" remains as specified above.