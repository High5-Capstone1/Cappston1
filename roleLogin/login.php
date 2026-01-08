<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md relative flex flex-col p-4 rounded-md text-black bg-white shadow-lg">
        <div class="text-2xl font-bold mb-2 text-[#1e0e4b] text-center">Welcome back to <span class="text-[#7747ff]">Mr.Softy Login </span></div>
        <div class="text-sm font-normal mb-4 text-center text-[#1e0e4b]">Log in to your account</div>
        
        <form action="/process.php" method="POST" class="flex flex-col gap-3">
            <div class="block relative"> 
                <label for="username" class="block text-gray-600 cursor-text text-sm leading-[140%] font-normal mb-2">Username</label>
                <input type="text" id="username" name="username" required class="rounded border border-gray-200 text-sm w-full font-normal leading-[18px] text-black tracking-[0px] appearance-none block h-11 m-0 p-[11px] focus:ring-2 ring-offset-2 ring-gray-900 outline-0">
            </div>
            
            <div class="block relative"> 
                <label for="password" class="block text-gray-600 cursor-text text-sm leading-[140%] font-normal mb-2">Password</label>
                <input type="password" id="password" name="password" required class="rounded border border-gray-200 text-sm w-full font-normal leading-[18px] text-black tracking-[0px] appearance-none block h-11 m-0 p-[11px] focus:ring-2 ring-offset-2 ring-gray-900 outline-0">
                   <button type="button"
                         onclick="togglePassword()"
                         class="absolute right-3 top-[38px] text-gray-500 hover:text-gray-700">
                         👁️
                        </button>
                        </div>
         
            <div>
                <a class="text-sm text-[#7747ff]" href="#">Login your account to continue</a>
            </div>
            
            <button type="submit" class="bg-[#7747ff] w-max m-auto px-6 py-2 rounded text-white text-sm font-normal">Login</button>
        </form>
        
       
    </div>
    <script>
function togglePassword() {
    const password = document.getElementById("password");
    password.type = password.type === "password" ? "text" : "password";
}
</script>
</body>
</html>