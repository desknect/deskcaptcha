# Desktop Integration — DeskCaptcha

> 🌐 [desknect.com](https://desknect.com) &nbsp;|&nbsp; 📖 [Official Docs](https://dcaptcha.desknect.com/api-documentacao) &nbsp;|&nbsp; ❤️ [Donate](https://desknect.com/donate)

DeskCaptcha can be embedded in desktop applications by running the API server locally or connecting to a remote instance.

---

## Option 1: Local Server (Recommended for standalone apps)

Run the PHP built-in server alongside your desktop app:

```bash
cd /path/to/deskcaptcha/public
php -S 127.0.0.1:8765
```

Your app connects to `http://127.0.0.1:8765/v1/captcha/generate`.  
Set `LOCAL_MODE=true` in `config/api.php` to disable CORS checks.

---

## Python Example (Tkinter)

```python
import tkinter as tk
from tkinter import ttk
import requests
from PIL import Image, ImageTk
from io import BytesIO

API = 'http://127.0.0.1:8765/v1'

class CaptchaWidget(tk.Frame):
    def __init__(self, master):
        super().__init__(master)
        self.token = ''
        self.img_label = tk.Label(self)
        self.img_label.pack()
        ttk.Button(self, text='Refresh', command=self.load).pack()
        self.entry = ttk.Entry(self)
        self.entry.pack()
        ttk.Button(self, text='Validate', command=self.validate).pack()
        self.status = tk.Label(self, text='')
        self.status.pack()
        self.load()

    def load(self):
        res   = requests.get(f'{API}/captcha/generate?scale=2&chars=4').json()
        self.token = res['data']['token']
        img_res = requests.get(res['data']['image_url'])
        img = Image.open(BytesIO(img_res.content))
        self.photo = ImageTk.PhotoImage(img)
        self.img_label.config(image=self.photo)
        self.entry.delete(0, tk.END)

    def validate(self):
        answer = self.entry.get()
        res = requests.post(f'{API}/captcha/validate',
                            json={'token': self.token, 'answer': answer}).json()
        if res['data']['valid']:
            self.status.config(text='✓ Correct!', fg='green')
        else:
            self.status.config(text='✗ Wrong, try again.', fg='red')
            self.load()

root = tk.Tk()
root.title('DeskCaptcha Demo')
CaptchaWidget(root).pack(padx=20, pady=20)
root.mainloop()
```

---

## C# / .NET Example

```csharp
using System.Net.Http;
using System.Text;
using System.Text.Json;

var client = new HttpClient();
const string API = "http://127.0.0.1:8765/v1";

// Generate
var genRes  = await client.GetStringAsync($"{API}/captcha/generate?scale=1&chars=4");
var genData = JsonSerializer.Deserialize<JsonElement>(genRes);
var token   = genData.GetProperty("data").GetProperty("token").GetString();
var imgUrl  = genData.GetProperty("data").GetProperty("image_url").GetString();

// Validate
var body    = JsonSerializer.Serialize(new { token, answer = "A3B7" });
var content = new StringContent(body, Encoding.UTF8, "application/json");
var valRes  = await client.PostAsync($"{API}/captcha/validate", content);
var valData = JsonSerializer.Deserialize<JsonElement>(await valRes.Content.ReadAsStringAsync());
bool valid  = valData.GetProperty("data").GetProperty("valid").GetBoolean();
```
