# Script para iniciar Aider con Ollama local
# Modelo: qwen2.5-coder:1.5b (optimizado para tu GPU)

# Usamos la imagen oficial de Aider y conectamos con Ollama por el host interno
docker run -it --rm `
    --volume ${PWD}:/app `
    -e OLLAMA_API_BASE=http://host.docker.internal:11434 `
    paulgauthier/aider:latest `
    --model ollama/qwen2.5-coder:1.5b `
    --no-git `
    --no-auto-commits
