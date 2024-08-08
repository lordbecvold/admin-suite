/** admin-suite: terminal component */
document.addEventListener("DOMContentLoaded", function() {
    // get html element list
    const commandContainer = document.getElementById('command-container')
    const terminal = document.getElementById('output-container')
    const commandInput = document.getElementById('command')
    const pathElement = document.getElementById('path')

    // current working directory
    let currentPath = ''

    // api url link
    const api_url = '/api/system/terminal'

    // command history
    let commandHistory = JSON.parse(localStorage.getItem('commandHistory')) || []
    let historyIndex = commandHistory.length

    // focus command input
    commandInput.focus()

    // update cwd on page load
    getCurrentPath()

    // update cwd
    function updatePath() {
        pathElement.textContent = currentPath
    }

    // scroll the bottom
    function scrollToBottom() { 
        terminal.scrollTop = terminal.scrollHeight
    }

    // fetch the current cwd from the server
    function getCurrentPath() {
        setTimeout(function() {
            const xhr = new XMLHttpRequest()
            xhr.open('POST', api_url, true)
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded')
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    currentPath = xhr.responseText
                    updatePath()
                }
            }
            xhr.send('command=get_current_path_1181517815187484')
        }, 50)
    }

    // event listener for keypress in the command input
    commandInput.addEventListener("keydown", function(e) {
        if (e.key === "Enter") {
            const command = this.value.trim()
            if (command.length > 0) {

                // display command in the terminal
                terminal.innerHTML += '<div class="command-history-prompt"><span class="text-blue-600">' + pathElement.textContent + '</span><span class="text-white">$<span class="last-command">' + command + '</span></span></div>'
                
                // save command to history
                commandHistory.push(command)
                localStorage.setItem('commandHistory', JSON.stringify(commandHistory))
                historyIndex = commandHistory.length

                // clear the input
                this.value = ''

                // execute the command
                executeCommand(command)

                // update cwd after cd command execution
                getCurrentPath()

                // scroll to the bottom of the terminal
                scrollToBottom()
            }
        // handle command history navigation up
        } else if (e.key === "ArrowUp") {
            if (historyIndex > 0) {
                historyIndex--
                commandInput.value = commandHistory[historyIndex]
            }
        // handle command history navigation down
        } else if (e.key === "ArrowDown") {
            if (historyIndex < commandHistory.length - 1) {
                historyIndex++
                commandInput.value = commandHistory[historyIndex]
            } else {
                historyIndex = commandHistory.length
                commandInput.value = ''
            }
        }
    })

    // event listener to focus on the command input when clicking outside of it
    document.addEventListener("click", function(e) {
        var isInsideTerminalComponent = e.target.closest('.component') !== null
        if (isInsideTerminalComponent && e.target !== commandInput) {
            commandInput.focus()
        }
    })

    // execute the entered command
    function executeCommand(command) {
        // set command to lower case
        command = command.toLowerCase()

        // clear terminal history
        if (command === 'clear') {
            terminal.innerHTML = ''
        } else {
            commandContainer.style.display = 'none'

            const xhr = new XMLHttpRequest()
            xhr.open('POST', api_url, true)
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded')
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        terminal.innerHTML += '<div>' + xhr.responseText + '</div>'
                        commandContainer.style.display = ''

                        // focus on the command input
                        commandInput.focus()
                    } else {
                        console.log(xhr.responseText)
                        terminal.innerHTML += '<div class="text-yellow-400">Error communicating with the API.</div>'
                        
                        // focus on the command input
                        commandInput.focus()
                    }
                }

                // scroll to the bottom of the terminal
                scrollToBottom()
            }
            
            // execute the command on the server
            xhr.send('command=' + encodeURIComponent(command))
        }
    }
})
