import socket
import ssl
import json
import os
import sys
import base64
import re
from datetime import datetime

class HTTPRequester:
    def __init__(self):
        self.url = ""
        self.method = "GET"
        self.headers = {
            "User-Agent": "KitchenGun/1.0",
            "Connection": "close"
        }
        self.body = ""
        self.timeout = 10
        self.follow_redirects = True
        self.max_redirects = 5
        self.verify_ssl = True
        self.config_file = "http_requester_config.json"

    def clear_screen(self):
        """Clear the terminal screen."""
        os.system('cls' if os.name == 'nt' else 'clear')

    def print_banner(self):
        """Print application banner."""
        self.clear_screen()
        print("=" * 60)
        print("""
        _  __  __        ____  ____ _________ _________ _______   
       (_)[  |[  |      |_   ||   _|  _   _  |  _   _  |_   __ \  
 .--.  __  | | | |  _   __| |__| | |_/ | | \_|_/ | | \_| | |__) | 
( (`\][  | | | | | [ \ [  |  __  |     | |       | |     |  ___/  
 `'.'. | | | | | |  \ '/ _| |  | |_   _| |_     _| |_   _| |_     
[\__) [___[___[___[\_:  |____||____| |_____|   |_____| |_____|    
                   \__.'                                          
""")
        print("=" * 60)
        print(f"Current URL: {self.url or 'Not set'}")
        print(f"Method: {self.method}")
        print("=" * 60)

    def main_menu(self):
        """Display the main menu and handle user input."""
        while True:
            self.print_banner()
            print("\nMAIN MENU:")
            print("1. Set URL")
            print("2. Set Method")
            print("3. Headers Menu")
            print("4. Body Menu")
            print("5. Advanced Settings")
            print("6. Execute Request")
            print("7. Save Configuration")
            print("8. Load Configuration")
            print("9. Generate cURL Command")
            print("0. Exit")
            
            choice = input("\nEnter your choice (0-9): ")
            
            if choice == "1":
                self.set_url()
            elif choice == "2":
                self.set_method()
            elif choice == "3":
                self.headers_menu()
            elif choice == "4":
                self.body_menu()
            elif choice == "5":
                self.advanced_settings_menu()
            elif choice == "6":
                self.execute_request()
            elif choice == "7":
                self.save_configuration()
            elif choice == "8":
                self.load_configuration()
            elif choice == "9":
                self.generate_curl()
            elif choice == "0":
                print("Exiting program. Goodbye!")
                sys.exit(0)
            else:
                input("Invalid choice. Press Enter to continue...")

    def set_url(self):
        """Set the target URL."""
        self.print_banner()
        print("\nSET URL:")
        current = f" (current: {self.url})" if self.url else ""
        url = input(f"Enter the URL{current}: ")
        
        if url:
            # Basic URL validation
            if not (url.startswith("http://") or url.startswith("https://")):
                url = "https://" + url
            
            self.url = url
            
            # Try to set Host header automatically
            try:
                from urllib.parse import urlparse
                parsed_url = urlparse(self.url)
                self.headers["Host"] = parsed_url.netloc
            except:
                pass  # If parsing fails, we'll let the user set the Host header manually
        
        input("URL updated. Press Enter to continue...")

    def set_method(self):
        """Set the HTTP method."""
        self.print_banner()
        print("\nSET METHOD:")
        print("1. GET")
        print("2. POST")
        print("3. PUT")
        print("4. DELETE")
        print("5. HEAD")
        print("6. OPTIONS")
        print("7. PATCH")
        print("8. Custom")
        
        methods = {
            "1": "GET",
            "2": "POST",
            "3": "PUT",
            "4": "DELETE",
            "5": "HEAD",
            "6": "OPTIONS",
            "7": "PATCH"
        }
        
        choice = input(f"\nSelect a method (current: {self.method}): ")
        
        if choice in methods:
            self.method = methods[choice]
        elif choice == "8":
            custom_method = input("Enter custom method: ").upper()
            if custom_method:
                self.method = custom_method
        
        input("Method updated. Press Enter to continue...")

    def headers_menu(self):
        """Display and manage request headers."""
        while True:
            self.print_banner()
            print("\nHEADERS MENU:")
            
            # Display current headers
            if self.headers:
                print("\nCurrent Headers:")
                for idx, (key, value) in enumerate(self.headers.items(), 1):
                    print(f"{idx}. {key}: {value}")
            else:
                print("\nNo headers set.")
            
            print("\nOptions:")
            print("a. Add/Update Header")
            print("r. Remove Header")
            print("c. Clear All Headers")
            print("u. Update User-Agent")
            print("d. Add Default Headers")
            print("b. Back to Main Menu")
            
            choice = input("\nEnter your choice: ").lower()
            
            if choice == 'a':
                key = input("Enter header name: ")
                value = input("Enter header value: ")
                if key:
                    self.headers[key] = value
                    print(f"Header '{key}' set to '{value}'")
            elif choice == 'r':
                key = input("Enter header name to remove: ")
                if key in self.headers:
                    del self.headers[key]
                    print(f"Header '{key}' removed")
                else:
                    print(f"Header '{key}' not found")
            elif choice == 'c':
                confirm = input("Are you sure you want to clear all headers? (y/n): ")
                if confirm.lower() == 'y':
                    self.headers = {}
                    print("All headers cleared")
            elif choice == 'u':
                agent = input("Enter User-Agent (leave empty for default 'KitchenGun/1.0'): ")
                self.headers["User-Agent"] = agent if agent else "KitchenGun/1.0"
                print(f"User-Agent set to: {self.headers['User-Agent']}")
            elif choice == 'd':
                self.headers.update({
                    "User-Agent": "KitchenGun/1.0",
                    "Accept": "*/*",
                    "Connection": "close"
                })
                print("Default headers added")
            elif choice == 'b':
                break
            
            input("Press Enter to continue...")

    def body_menu(self):
        """Display and manage request body."""
        while True:
            self.print_banner()
            print("\nBODY MENU:")
            
            # Display current body (truncated if too long)
            if self.body:
                print("\nCurrent Body:")
                body_preview = self.body[:200] + "..." if len(self.body) > 200 else self.body
                print(body_preview)
                print(f"\nBody Length: {len(self.body)} characters")
            else:
                print("\nNo body set.")
            
            print("\nOptions:")
            print("1. Set Plain Text Body")
            print("2. Set JSON Body")
            print("3. Set Form Data")
            print("4. Set File Content as Body")
            print("5. Clear Body")
            print("6. Back to Main Menu")
            
            choice = input("\nEnter your choice (1-6): ")
            
            if choice == "1":
                print("\nEnter plain text body (type 'END' on a new line when finished):")
                lines = []
                while True:
                    line = input()
                    if line == "END":
                        break
                    lines.append(line)
                
                self.body = "\n".join(lines)
                # Update Content-Type header if body is set
                if self.body:
                    self.headers["Content-Type"] = "text/plain"
                    self.headers["Content-Length"] = str(len(self.body))
            
            elif choice == "2":
                try:
                    print("\nEnter JSON body (type 'END' on a new line when finished):")
                    lines = []
                    while True:
                        line = input()
                        if line == "END":
                            break
                        lines.append(line)
                    
                    json_text = "\n".join(lines)
                    # Validate JSON
                    json.loads(json_text)
                    self.body = json_text
                    self.headers["Content-Type"] = "application/json"
                    self.headers["Content-Length"] = str(len(self.body))
                    print("Valid JSON body set")
                except json.JSONDecodeError as e:
                    print(f"Invalid JSON: {e}")
            
            elif choice == "3":
                form_data = {}
                print("\nEnter form data (empty key to finish):")
                while True:
                    key = input("Key: ")
                    if not key:
                        break
                    value = input("Value: ")
                    form_data[key] = value
                
                if form_data:
                    form_body = "&".join(f"{k}={v}" for k, v in form_data.items())
                    self.body = form_body
                    self.headers["Content-Type"] = "application/x-www-form-urlencoded"
                    self.headers["Content-Length"] = str(len(self.body))
                    print("Form data body set")
            
            elif choice == "4":
                file_path = input("\nEnter path to file: ")
                try:
                    with open(file_path, 'rb') as f:
                        self.body = f.read().decode('utf-8', errors='replace')
                    
                    self.headers["Content-Length"] = str(len(self.body))
                    
                    # Try to guess content type from file extension
                    extension = os.path.splitext(file_path)[1].lower()
                    content_types = {
                        '.json': 'application/json',
                        '.xml': 'application/xml',
                        '.txt': 'text/plain',
                        '.html': 'text/html',
                        '.css': 'text/css',
                        '.js': 'application/javascript'
                    }
                    
                    if extension in content_types:
                        self.headers["Content-Type"] = content_types[extension]
                    else:
                        # Ask user for content type
                        content_type = input("Enter Content-Type (leave empty for auto-detect): ")
                        if content_type:
                            self.headers["Content-Type"] = content_type
                    
                    print(f"File loaded as body ({len(self.body)} bytes)")
                except Exception as e:
                    print(f"Error loading file: {e}")
            
            elif choice == "5":
                self.body = ""
                if "Content-Type" in self.headers:
                    del self.headers["Content-Type"]
                if "Content-Length" in self.headers:
                    del self.headers["Content-Length"]
                print("Body cleared")
            
            elif choice == "6":
                break
                
            input("Press Enter to continue...")

    def advanced_settings_menu(self):
        """Display and manage advanced request settings."""
        while True:
            self.print_banner()
            print("\nADVANCED SETTINGS:")
            print(f"1. Timeout: {self.timeout} seconds")
            print(f"2. Follow Redirects: {'Yes' if self.follow_redirects else 'No'}")
            print(f"3. Max Redirects: {self.max_redirects}")
            print(f"4. Verify SSL: {'Yes' if self.verify_ssl else 'No'}")
            print("5. Back to Main Menu")
            
            choice = input("\nEnter your choice (1-5): ")
            
            if choice == "1":
                try:
                    timeout = input(f"Enter timeout in seconds (current: {self.timeout}): ")
                    if timeout:
                        self.timeout = int(timeout)
                        print(f"Timeout set to {self.timeout} seconds")
                except ValueError:
                    print("Invalid input. Please enter a number.")
            
            elif choice == "2":
                current = "Yes" if self.follow_redirects else "No"
                choice = input(f"Follow redirects? (y/n) (current: {current}): ").lower()
                if choice in ('y', 'n'):
                    self.follow_redirects = (choice == 'y')
                    print(f"Follow redirects set to: {self.follow_redirects}")
            
            elif choice == "3":
                try:
                    redirects = input(f"Enter max redirects (current: {self.max_redirects}): ")
                    if redirects:
                        self.max_redirects = int(redirects)
                        print(f"Max redirects set to {self.max_redirects}")
                except ValueError:
                    print("Invalid input. Please enter a number.")
            
            elif choice == "4":
                current = "Yes" if self.verify_ssl else "No"
                choice = input(f"Verify SSL certificates? (y/n) (current: {current}): ").lower()
                if choice in ('y', 'n'):
                    self.verify_ssl = (choice == 'y')
                    print(f"Verify SSL set to: {self.verify_ssl}")
            
            elif choice == "5":
                break
            
            input("Press Enter to continue...")

    def parse_url(self, url):
        """Parse URL into components."""
        if "://" not in url:
            url = "https://" + url
        
        protocol, rest = url.split("://", 1)
        is_https = protocol.lower() == "https"
        
        if "/" in rest:
            host_port, path = rest.split("/", 1)
            path = "/" + path
        else:
            host_port = rest
            path = "/"
        
        if ":" in host_port:
            host, port = host_port.split(":", 1)
            port = int(port)
        else:
            host = host_port
            port = 443 if is_https else 80
        
        return {
            "protocol": protocol,
            "is_https": is_https,
            "host": host,
            "port": port,
            "path": path
        }

    def execute_request(self):
        """Execute the HTTP request."""
        self.print_banner()
        print("\nEXECUTING REQUEST...")
        
        if not self.url:
            print("Error: URL not set")
            input("Press Enter to continue...")
            return
        
        try:
            # Parse URL
            url_parts = self.parse_url(self.url)
            
            # Prepare request line
            request_path = url_parts["path"] if url_parts["path"] else "/"
            request_line = f"{self.method} {request_path} HTTP/1.1\r\n"
            
            # Prepare headers
            if "Host" not in self.headers:
                self.headers["Host"] = url_parts["host"]
            
            if self.body and "Content-Length" not in self.headers:
                self.headers["Content-Length"] = str(len(self.body))
            
            headers_str = "".join(f"{key}: {value}\r\n" for key, value in self.headers.items())
            
            # Combine request components
            request = request_line + headers_str + "\r\n" + (self.body or "")
            
            # Create socket
            sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            sock.settimeout(self.timeout)
            
            # Wrap socket with SSL if HTTPS
            if url_parts["is_https"]:
                context = ssl.create_default_context()
                if not self.verify_ssl:
                    context.check_hostname = False
                    context.verify_mode = ssl.CERT_NONE
                sock = context.wrap_socket(sock, server_hostname=url_parts["host"])
            
            # Connect and send request
            print(f"Connecting to {url_parts['host']}:{url_parts['port']}...")
            sock.connect((url_parts['host'], url_parts['port']))
            print("Connected. Sending request...")
            sock.sendall(request.encode())
            
            # Receive response
            print("Receiving response...")
            response_data = b""
            while True:
                chunk = sock.recv(4096)
                if not chunk:
                    break
                response_data += chunk
            
            sock.close()
            
            # Parse response
            if b"\r\n\r\n" in response_data:
                headers_data, body_data = response_data.split(b"\r\n\r\n", 1)
                headers_text = headers_data.decode('utf-8', errors='replace')
                
                # Extract status line
                status_line = headers_text.split("\r\n")[0]
                
                # Try to decode body with appropriate charset
                content_type_match = re.search(r"Content-Type:.*?charset=([^\s;]+)", headers_text, re.IGNORECASE)
                charset = content_type_match.group(1) if content_type_match else 'utf-8'
                
                try:
                    body_text = body_data.decode(charset, errors='replace')
                except LookupError:
                    # If charset is not recognized, fallback to utf-8
                    body_text = body_data.decode('utf-8', errors='replace')
                
                # Display response details
                print("\n" + "=" * 60)
                print("RESPONSE RECEIVED:")
                print("=" * 60)
                print(status_line)
                
                # Parse and display headers
                print("\nHEADERS:")
                for line in headers_text.split("\r\n")[1:]:  # Skip status line
                    if line:
                        print(line)
                
                # Display body (truncated if too large)
                print("\nBODY:")
                if len(body_text) > 1000:
                    print(body_text[:1000] + "...\n[Body truncated, total size: {} bytes]".format(len(body_data)))
                else:
                    print(body_text)
                
                # Save response to file option
                save_choice = input("\nSave response to file? (y/n): ").lower()
                if save_choice == 'y':
                    filename = input("Enter filename (default: response.txt): ") or "response.txt"
                    with open(filename, 'wb') as f:
                        f.write(response_data)
                    print(f"Response saved to {filename}")
            else:
                print("\nInvalid or empty response received")
            
        except Exception as e:
            print(f"\nError executing request: {e}")
        
        input("\nPress Enter to continue...")

    def save_configuration(self):
        """Save current configuration to file."""
        self.print_banner()
        print("\nSAVE CONFIGURATION:")
        
        filename = input(f"Enter filename (default: {self.config_file}): ") or self.config_file
        
        config = {
            "url": self.url,
            "method": self.method,
            "headers": self.headers,
            "body": self.body,
            "timeout": self.timeout,
            "follow_redirects": self.follow_redirects,
            "max_redirects": self.max_redirects,
            "verify_ssl": self.verify_ssl,
            "saved_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        }
        
        try:
            with open(filename, 'w') as f:
                json.dump(config, f, indent=2)
            print(f"Configuration saved to {filename}")
        except Exception as e:
            print(f"Error saving configuration: {e}")
        
        input("Press Enter to continue...")

    def load_configuration(self):
        """Load configuration from file."""
        self.print_banner()
        print("\nLOAD CONFIGURATION:")
        
        filename = input(f"Enter filename (default: {self.config_file}): ") or self.config_file
        
        try:
            with open(filename, 'r') as f:
                config = json.load(f)
            
            self.url = config.get("url", "")
            self.method = config.get("method", "GET")
            self.headers = config.get("headers", {})
            self.body = config.get("body", "")
            self.timeout = config.get("timeout", 10)
            self.follow_redirects = config.get("follow_redirects", True)
            self.max_redirects = config.get("max_redirects", 5)
            self.verify_ssl = config.get("verify_ssl", True)
            
            print(f"Configuration loaded from {filename}")
            if "saved_at" in config:
                print(f"Configuration was saved at: {config['saved_at']}")
        except FileNotFoundError:
            print(f"File {filename} not found")
        except json.JSONDecodeError:
            print(f"Invalid JSON in {filename}")
        except Exception as e:
            print(f"Error loading configuration: {e}")
        
        input("Press Enter to continue...")

    def generate_curl(self):
        """Generate equivalent cURL command."""
        self.print_banner()
        print("\nGENERATE CURL COMMAND:")
        
        if not self.url:
            print("Error: URL not set")
            input("Press Enter to continue...")
            return
        
        # Start building the curl command
        curl_cmd = ["curl"]
        
        # Add method if not GET
        if self.method != "GET":
            curl_cmd.append(f"-X {self.method}")
        
        # Add headers
        for key, value in self.headers.items():
            # Escape quotes in header values
            value = value.replace('"', '\\"')
            curl_cmd.append(f'-H "{key}: {value}"')
        
        # Add body if present
        if self.body:
            # Escape quotes and newlines in body
            escaped_body = self.body.replace('"', '\\"').replace('\n', '\\n')
            curl_cmd.append(f'-d "{escaped_body}"')
        
        # Add timeout
        curl_cmd.append(f"--connect-timeout {self.timeout}")
        
        # Add follow redirects flag
        if self.follow_redirects:
            curl_cmd.append("-L")
        
        # Add SSL verification flag
        if not self.verify_ssl:
            curl_cmd.append("-k")
        
        # Add URL (quoted to handle special characters)
        curl_cmd.append(f'"{self.url}"')
        
        # Join all parts with spaces
        curl_command = " ".join(curl_cmd)
        
        print("\nGenerated cURL Command:")
        print("=" * 60)
        print(curl_command)
        print("=" * 60)
        
        # Copy to clipboard option
        try:
            import pyperclip
            copy_choice = input("\nCopy to clipboard? (y/n): ").lower()
            if copy_choice == 'y':
                pyperclip.copy(curl_command)
                print("Command copied to clipboard")
        except ImportError:
            print("\nNote: Install 'pyperclip' package to enable clipboard functionality")
        
        # Save to file option
        save_choice = input("\nSave to file? (y/n): ").lower()
        if save_choice == 'y':
            filename = input("Enter filename (default: curl_command.txt): ") or "curl_command.txt"
            try:
                with open(filename, 'w') as f:
                    f.write(curl_command)
                print(f"Command saved to {filename}")
            except Exception as e:
                print(f"Error saving command: {e}")
        
        input("\nPress Enter to continue...")


if __name__ == "__main__":
    requester = HTTPRequester()
    requester.main_menu()
